<?php

namespace App\Services;

use App\Http\Controllers\LeaveAccrualService;
use App\Http\Services\WhatsAppService;
use App\Models\EmployeeDailySchedule;
use App\Models\EmployeeExtraOff;
use App\Models\EmployeePhAdjustment;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HrisWhatsAppAgent
{
    public function __construct(
        private readonly HrisDatabaseQueryAgent $dbQueryAgent,
        private readonly AiEngineManager $aiEngine,
        private readonly WhatsAppService $whatsApp,
        private readonly LeaveAccrualService $leaveAccrualService
    ) {}

    public function handleWebhook(array $payload): array
    {
        if (! (bool) config('services.hris_agent.enabled', true)) {
            return ['status' => 'ignored', 'reason' => 'agent_disabled'];
        }

        if ($this->isFromMe($payload)) {
            return ['status' => 'ignored', 'reason' => 'from_me'];
        }

        $sender = $this->extractSender($payload);
        $message = $this->extractMessage($payload);

        if ($sender === '' || $message === '') {
            return ['status' => 'ignored', 'reason' => 'missing_sender_or_message'];
        }

        if (! $this->senderAllowed($sender)) {
            return ['status' => 'ignored', 'reason' => 'sender_not_allowed'];
        }

        $question = $this->stripTrigger($message);

        if ($question === null || $question === '') {
            return ['status' => 'ignored', 'reason' => 'trigger_not_matched'];
        }

        // Rate Limiter: Maksimal 20 pesan per menit per nomor/pengirim
        $cleanSenderKey = preg_replace('/[^a-zA-Z0-9]/', '', $sender);
        $rateLimitKey = 'wa_ai_agent_limit:' . $cleanSenderKey;
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($rateLimitKey, 20)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($rateLimitKey);
            $this->sendReply($sender, "Mohon tunggu {$seconds} detik sebelum mengirim pesan lagi ya.");
            return ['status' => 'rate_limited', 'retry_after' => $seconds];
        }
        \Illuminate\Support\Facades\RateLimiter::hit($rateLimitKey, 60);

        $karyawan = $this->findKaryawanByPayload($payload, $sender);

        $historyKey = 'wa_ai_agent_history:' . $cleanSenderKey;
        $history = Cache::get($historyKey, []);

        $answer = $this->answer($question, $karyawan, $sender, $history);
        $sent = $this->sendReply($sender, $answer);

        // Simpan riwayat percakapan (Multi-turn Context) maksimal 6 percakapan terakhir
        $history[] = ['role' => 'user', 'parts' => [['text' => $question]]];
        $history[] = ['role' => 'model', 'parts' => [['text' => $answer]]];
        if (count($history) > 6) {
            $history = array_slice($history, -6);
        }
        Cache::put($historyKey, $history, now()->addMinutes(30));

        return [
            'status' => $sent ? 'sent' : 'send_failed',
            'sender' => $sender,
            'karyawan_nik' => $karyawan?->nik,
            'question' => $question,
        ];
    }

    private function answer(string $question, ?Karyawan $karyawan, string $sender, array $history = []): string
    {
        $normalized = $this->normalizeText($question);
        $isFirstChat = empty($history);

        // 1. Cek apakah ada aksi transaksional khusus (Eskalasi IT, Reset Session, Reset Password)
        $actionAnswer = $this->handleDirectActions($normalized, $karyawan, $history);
        if ($actionAnswer !== null) {
            return $actionAnswer;
        }

        // 2. Cek database FAQ / Knowledge Base dinamis
        $faqAnswer = $this->matchFaqDatabase($normalized);
        if ($faqAnswer !== null) {
            return $faqAnswer;
        }

        // 3. Gunakan AI Database Query Agent (Autonomous Text-to-SQL + Natural Human Language)
        $dbAgentAnswer = $this->dbQueryAgent->queryAndAnswer($question, $karyawan, $history);
        if ($dbAgentAnswer !== null && trim($dbAgentAnswer) !== '') {
            return $dbAgentAnswer;
        }

        // 4. Fallback ke LLM General Knowledge & Sapaan Alami (Round-Robin: OpenRouter / TokenRouter / Gemini)
        $systemPrompt = $this->buildSystemPrompt($karyawan, $isFirstChat);
        
        $aiResponse = $this->aiEngine->chat($question, $systemPrompt, $history);
        if ($aiResponse !== null && $aiResponse !== '') {
            return $aiResponse;
        }

        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";
        return "Maaf ya {$sapaan}, Haris belum bisa menemukan data tersebut di sistem. Ada hal lain yang bisa Haris bantu? 😊";
    }

    private function normalizeText(string $text): string
    {
        $str = Str::of($text)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();

        $words = explode(' ', $str);
        $typoDictionary = [
            // Saldo / Sisa
            'sado' => 'saldo', 'sldo' => 'saldo', 'slado' => 'saldo', 'sald' => 'saldo',
            'sisaa' => 'sisa', 'sia' => 'sisa', 'sisah' => 'sisa',
            'brp' => 'berapa', 'brapa' => 'berapa', 'berpa' => 'berapa', 'brpa' => 'berapa',
            // Cuti
            'cti' => 'cuti', 'cuuti' => 'cuti', 'cuty' => 'cuti', 'cute' => 'cuti',
            // Password
            'pw' => 'password', 'pasword' => 'password', 'pass' => 'password', 'paswrd' => 'password',
            'paswod' => 'password', 'paswot' => 'password', 'passwrd' => 'password', 'sandi' => 'password',
            // Session
            'sesi' => 'session', 'sesion' => 'session', 'seson' => 'session', 'season' => 'session', 'seasson' => 'session',
            // Jadwal & Shift
            'jwadal' => 'jadwal', 'jadwl' => 'jadwal', 'jdwl' => 'jadwal', 'jadual' => 'jadwal',
            'shif' => 'shift', 'sift' => 'shift', 'syift' => 'shift', 'shiff' => 'shift',
            // Waktu
            'bsk' => 'besok', 'beso' => 'besok', 'esok' => 'besok', 'bsok' => 'besok',
            'skrg' => 'hari ini', 'skrang' => 'hari ini', 'seakrang' => 'hari ini', 'sekarang' => 'hari ini',
            // Kontrak
            'kotrak' => 'kontrak', 'kontrk' => 'kontrak', 'kontra' => 'kontrak',
            // Pertanyaan & Singkatan
            'gmana' => 'gimana', 'gimna' => 'gimana', 'gmn' => 'gimana', 'bgmn' => 'bagaimana',
            'ga' => 'tidak', 'gak' => 'tidak', 'nggak' => 'tidak', 'gabisa' => 'tidak bisa', 'tdk' => 'tidak',
            'dgn' => 'dengan', 'sy' => 'saya', 'aku' => 'saya', 'gw' => 'saya', 'gua' => 'saya',
            // Tim / Bawahan / Atasan
            'bwah' => 'bawahan', 'bawahn' => 'bawahan', 'bwhn' => 'bawahan', 'anakbuah' => 'bawahan',
            'atsn' => 'atasan', 'atsan' => 'atasan',
        ];

        $corrected = [];
        foreach ($words as $w) {
            $corrected[] = $typoDictionary[$w] ?? $w;
        }

        return implode(' ', $corrected);
    }

    private function handleDirectActions(string $question, ?Karyawan $karyawan, array $history = []): ?string
    {
        $normalized = Str::of($question)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();

        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";
        $isFirstChat = empty($history);

        // -------------------------------------------------------------
        // Aksi 0: Eskalasi Bantuan Langsung ke Tim IT (Forward ke Grup IT)
        // -------------------------------------------------------------
        $isAskingHumanIt = (
            (str_contains($normalized, 'bicara') || str_contains($normalized, 'ngobrol') || str_contains($normalized, 'ngomong') || str_contains($normalized, 'chat') || str_contains($normalized, 'hubungi') || str_contains($normalized, 'kontak') || str_contains($normalized, 'panggil') || str_contains($normalized, 'sambungkan') || str_contains($normalized, 'tanya langsung'))
            && (str_contains($normalized, 'it') || str_contains($normalized, 'admin') || str_contains($normalized, 'manusia') || str_contains($normalized, 'orang') || str_contains($normalized, 'staf') || str_contains($normalized, 'operator'))
        )
        || str_contains($normalized, 'butuh bantuan it')
        || str_contains($normalized, 'bantuan tim it')
        || str_contains($normalized, 'tolong it')
        || str_contains($normalized, 'error 500')
        || str_contains($normalized, 'bug aplikasi')
        || str_contains($normalized, 'sistem down');

        if ($isAskingHumanIt) {
            $nama = $karyawan?->nama_karyawan ?: 'Karyawan';
            $nik = $karyawan?->nik ?: '-';
            $divisi = ($karyawan?->jabatan ?: $karyawan?->departement) ?: '-';
            $phone = $karyawan?->no_hp ?: '';
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
            if (str_starts_with($cleanPhone, '0')) {
                $cleanPhone = '62' . substr($cleanPhone, 1);
            }
            $waLink = $cleanPhone !== '' ? "https://wa.me/{$cleanPhone}" : "-";
            $waktu = now()->locale('id')->translatedFormat('d F Y, H:i') . ' WIB';

            // Rangkum kendala asli berdasarkan riwayat percakapan sebelumnya
            $kendalaText = $this->summarizeTicketIssue($question, $history);

            $ticketMsg = "🚨 *[TIKET BANTUAN IT BARU]*\n";
            $ticketMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $ticketMsg .= "👤 *Karyawan* : {$nama}\n";
            $ticketMsg .= "🆔 *NIK*      : {$nik}\n";
            $ticketMsg .= "🏢 *Divisi*   : {$divisi}\n";
            if ($waLink !== '-') {
                $ticketMsg .= "📱 *WhatsApp* : {$waLink}\n";
            }
            $ticketMsg .= "⏰ *Waktu*    : {$waktu}\n\n";
            $ticketMsg .= "💬 *Rincian Kendala / Topik*:\n";
            $ticketMsg .= "{$kendalaText}\n";
            $ticketMsg .= "━━━━━━━━━━━━━━━━━━━━\n";
            $ticketMsg .= "👉 Tim IT bisa langsung tap link WhatsApp di atas untuk menghubungi karyawan.";

            $itGroupId = (string) config('services.whatsapp.it_support_group_id');
            if ($itGroupId !== '') {
                $this->sendReply($itGroupId, $ticketMsg);
            }

            return "Siap {$sapaan}! Pesan dan permintaan bantuanmu sudah aku teruskan langsung ke Tim IT ya 📩.\n\nTim IT akan segera mengecek dan menghubungimu melalui WhatsApp ini. Mohon ditunggu sebentar ya {$sapaan}!";
        }

        // -------------------------------------------------------------
        // Aksi 1: Reset / Kick Active Session (Langsung atau setelah konfirmasi)
        // -------------------------------------------------------------
        $isDirectReset = str_contains($normalized, 'reset session')
            || str_contains($normalized, 'clear session')
            || str_contains($normalized, 'hapus session')
            || str_contains($normalized, 'hapus sesi')
            || str_contains($normalized, 'kick session')
            || str_contains($normalized, 'reset token')
            || str_contains($normalized, 'bantu reset')
            || str_contains($normalized, 'tolong reset')
            || (str_contains($normalized, 'aktif') && (str_contains($normalized, 'browser') || str_contains($normalized, 'perangkat') || str_contains($normalized, 'device') || str_contains($normalized, 'lain') || str_contains($normalized, 'sedang aktif')))
            || str_contains($normalized, 'nyangkut');

        $cacheStateKey = "wa_agent_state:" . ($karyawan ? $karyawan->nik : 'unknown');
        $isAwaitingReset = \Illuminate\Support\Facades\Cache::get($cacheStateKey) === 'awaiting_session_reset';
        $isYesAnswer = in_array($normalized, ['iya', 'ya', 'iya kak', 'ya kak', 'betul', 'bener', 'bener kak', 'betul kak', 'iya bener', 'iya betul', 'reset', 'iya tolong', 'tolong ya', 'bantu ya', 'oke tolong', 'ok tolong'], true);

        if ($isDirectReset || ($isAwaitingReset && $isYesAnswer)) {
            \Illuminate\Support\Facades\Cache::forget($cacheStateKey);

            if (! $karyawan) {
                return "Maaf ya {$sapaan}, nomor WhatsApp ini belum terdaftar di sistem HRIS. Coba konfirmasi ke HRD untuk update nomor telepon ya.";
            }

            $user = User::where('username', $karyawan->nik)->first();
            if (! $user) {
                return "Akun portal untuk NIK {$karyawan->nik} belum ditemukan nih. Boleh hubungi tim IT ya.";
            }

            $user->tokens()->where('name', 'hris-fe')->delete();

            return "Siap {$sapaan}, sesi login akun kamu (NIK: {$karyawan->nik}) sudah berhasil aku reset ya. Silakan coba login kembali sekarang di https://hr.hompimplay.id.";
        }

        // -------------------------------------------------------------
        // Aksi 1.1: Deteksi Karyawan Mengeluh Tidak Bisa Login (Proaktif Tanya)
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'ga bisa login')
            || str_contains($normalized, 'gabisa login')
            || str_contains($normalized, 'tidak bisa login')
            || str_contains($normalized, 'gagal login')
            || str_contains($normalized, 'susah login')
            || str_contains($normalized, 'can t login')
            || (str_contains($normalized, 'login') && (str_contains($normalized, 'aplikasi') || str_contains($normalized, 'web') || str_contains($normalized, 'portal') || str_contains($normalized, 'error') || str_contains($normalized, 'gagal')))
        ) {
            \Illuminate\Support\Facades\Cache::put($cacheStateKey, 'awaiting_session_reset', 300);

            $prefix = $isFirstChat ? "Halo {$sapaan}, " : "{$sapaan}, ";
            return "{$prefix}apakah saat mau login muncul notifikasi *'Akun Anda sedang aktif di perangkat/browser lain'*?\n\nKalau iya, balas chat ini dengan ketik *Iya* atau *Reset*, nanti langsung aku bantu bebaskan sesinya sekarang ya.";
        }

        // -------------------------------------------------------------
        // Aksi 2: Lupa / Reset Password (Langsung reset ke default 12345678)
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'lupa password')
            || str_contains($normalized, 'reset password')
            || str_contains($normalized, 'lupa sandi')
            || str_contains($normalized, 'ganti password')
            || str_contains($normalized, 'reset pass')
            || str_contains($normalized, 'reset kata sandi')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di sistem HRIS nih.";
            }

            $user = User::where('username', $karyawan->nik)->first();
            if (! $user) {
                return "Akun portal untuk NIK {$karyawan->nik} belum ditemukan nih. Boleh hubungi tim IT ya.";
            }

            // Reset password ke default 12345678
            $user->password = '12345678';
            $user->must_change_password = true;
            $user->save();
            $user->tokens()->delete();

            return "Password akun portal kamu (NIK: {$karyawan->nik}) sudah berhasil di-reset ke default: *12345678*.\n\nSilakan coba login kembali di https://hr.hompimplay.id lalu ubah kata sandi di profil demi keamanan ya.";
        }

        return null;
    }

    private function buildSystemPrompt(?Karyawan $karyawan, bool $isFirstChat = false): string
    {
        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";

        $prompt = "IDENTITAS DIRI & PERSONA:\n";
        $prompt .= "- Nama kamu: Haris (singkatan & representasi dari HRIS).\n";
        $prompt .= "- Peran kamu: IT AI Agent internal di HomPim Play yang bertugas melayani chat WhatsApp karyawan seputar HRIS.\n";
        $prompt .= "- Rekan chat kamu: {$sapaan}.\n\n";

        $prompt .= "ATURAN GAYA BICARA PERCAKAPAN WHATSAPP (RAMAH, TO-THE-POINT & NATURAL):\n";
        $prompt .= "1. DILARANG KERAS MEMBUKA DENGAN KALIMAT TEMPLATE KAKU SEPERTI 'Halo Kak Fajar! Aku Haris dari IT AI HRIS. Ada yang bisa kubantu?' di setiap respon!\n";
        $prompt .= "   - JIKA user HANYA menyapa (contoh: 'halo', 'hai', 'pagi kak', 'assalamualaikum', 'siang'): Balas sapaan secara ramah dan tanyakan keperluannya ('Halo {$sapaan}! Ada yang bisa Haris bantu seputar HRIS hari ini? 😊').\n";
        $prompt .= "   - JIKA user LANGSUNG MENANYAKAN PERTANYAAN (contoh: 'saldo ph berapa?', 'pernah ajukan cuti kapan?', 'absen masuk jam berapa?', 'cara dapat eo gimana?'): LANGSUNG JAWAB PERTANYAANNYA secara to-the-point, ramah, dan santai (contoh: 'Sisa saldo PH {$sapaan} saat ini masih ada 3 hari yaa 😊'). JANGAN PERNAH menyisipkan kalimat perkenalan berulang!\n";
        $prompt .= "2. FOKUS HANYA PADA YANG DITANYAKAN (ZERO TANGENT):\n";
        $prompt .= "   - Tanya Saldo Cuti -> Jawab HANYA cuti tahunan.\n";
        $prompt .= "   - Tanya Saldo PH -> Jawab HANYA saldo PH.\n";
        $prompt .= "   - Tanya Saldo Extra Off -> Jawab HANYA saldo Extra Off.\n";
        $prompt .= "   - Tanya Jam Masuk -> Jawab HANYA jam masuknya.\n";
        $prompt .= "3. Selalu panggil '{$sapaan}' secara santai dan akrab ('Udah kok', 'Iya Kak', 'Aman yaa', 'Semangat!') dan boleh gunakan 1-2 emoji (😊, 👍, ✨).\n";
        $prompt .= "4. DILARANG menggunakan format list formulir kaku (* ...) kecuali karyawan meminta rincian riwayat banyak baris/hari.\n";
        $prompt .= "5. JANGAN PERNAH mengatakan 'sebentar ya aku cek dulu / nanti aku kabari lagi' karena chat dijawab seketika secara real-time.\n";
        $prompt .= "6. Password default portal HRIS adalah 12345678 (delapan digit: 12345678, BUKAN 123456).\n";
        $prompt .= "7. BATASAN TOPIK: HANYA layani pertanyaan seputar HRIS, absensi, jadwal kerja, cuti, izin, lembur, info kontrak, slip gaji, dan SOP kantor.\n";

        $prompt .= "\nKNOWLEDGE BASE & LOGIKA BISNIS HRIS HOMPIM PLAY (SOP RESMI):\n";
        $prompt .= "- CARA DAPAT CUTI TAHUNAN & SYARATNYA:\n";
        $prompt .= "  * Hak cuti tahunan BARU AKTIF setelah karyawan mencapai masa kerja minimal 1 tahun (12 bulan) sejak tanggal bergabung (join_date).\n";
        $prompt .= "  * Setelah 1 tahun masa kerja, saldo bertambah otomatis +1 hari setiap bulan (pada tanggal yang sama dengan join_date).\n";
        $prompt .= "  * Masa berlaku saldo cuti mengikuti tanggal akhir kontrak aktif karyawan.\n";
        $prompt .= "  * Jika ada yang tanya 'kenapa cuti saya masih 0?', cek join_date di profil bawah. Jika masa kerjanya belum genap 1 tahun, jelaskan dengan ramah bahwa hak cuti tahunan mulai bertambah setelah 1 tahun masa kerja.\n";
        $prompt .= "- CARA DAPAT PH (PUBLIC HOLIDAY):\n";
        $prompt .= "  * Saldo PH diperoleh jika karyawan MASUK BEKERJA (ada scan absen fingerspot) pada hari libur nasional / tanggal merah resmi kalender kantor.\n";
        $prompt .= "  * Setiap 1 hari masuk di tanggal merah menghasilkan kompensasi 1 hari saldo PH yang berlaku selama 90 hari (3 bulan) untuk diambil sebagai hari libur pengganti lewat menu Pengajuan PH.\n";
        $prompt .= "- CARA DAPAT EXTRA OFF (EO):\n";
        $prompt .= "  * Saldo Extra Off diperoleh dari akumulasi kelebihan jam kerja / kompensasi lembur bulanan yang diatur oleh HRD. Berlaku selama 3 bulan.\n";
        $prompt .= "- CUTI NORMATIF:\n";
        $prompt .= "  * Cuti khusus tanpa potong saldo (Menikah 3 hari, Melahirkan 3 bulan, Istri Melahirkan 2 hari, Duka Cita 2 hari, dll.) diajukan lewat menu Pengajuan Cuti dengan memilih jenis Cuti Normatif.\n";
        $prompt .= "- ALUR APPROVAL:\n";
        $prompt .= "  * CUTI, PH, EXTRA OFF, IZIN, SAKIT: Diajukan sendiri oleh karyawan di https://hr.hompimplay.id dan disetujui (approval) oleh ATASAN LANGSUNG.\n";
        $prompt .= "  * LEMBUR (SPL): Hanya bisa diajukan oleh ATASAN LANGSUNG untuk menugaskan bawahan langsungnya lewat menu 'Pengajuan Lembur' di portal HRIS. Karyawan staf biasa tidak memiliki menu ini.\n";
        $prompt .= "- TROUBLESHOOTING MENU HILANG:\n";
        $prompt .= "  * Jika staf mengeluh menu lembur tidak ada, jelaskan bahwa menu lembur hanya muncul di akun Atasan/Supervisor yang punya bawahan.\n";
        $prompt .= "  * Jika atasan mengeluh menu tidak ada, sarankan logout lalu login ulang di portal HRIS.\n";

        if ($karyawan) {
            $prompt .= "\n[Info Karyawan yang Bertanya]\n";
            $prompt .= "Nama Lengkap: {$karyawan->nama_karyawan}\n";
            $prompt .= "NIK: {$karyawan->nik}\n";
            $prompt .= "Jabatan: " . ($karyawan->jabatan ?: $karyawan->posisi ?: '-') . "\n";
            $prompt .= "Divisi: " . ($karyawan->departement ?: $karyawan->divisi ?: '-') . "\n";
            $joinDateStr = $karyawan->join_date ? \Carbon\Carbon::parse($karyawan->join_date)->locale('id')->translatedFormat('d F Y') : '-';
            $prompt .= "Tanggal Bergabung (Join Date): {$joinDateStr}\n";

            $atasan = $karyawan->atasanLangsung;
            if ($atasan) {
                $prompt .= "Atasan Langsung: {$atasan->nama_karyawan} (" . ($atasan->jabatan ?: '-') . ")\n";
            }

            $subordinates = Karyawan::query()
                ->where(function ($q) use ($karyawan) {
                    $q->where('atasan_langsung_nik', $karyawan->nik)
                        ->orWhere('atasan_tidak_langsung_nik', $karyawan->nik);
                })
                ->where(function ($q) {
                    $q->whereNull('status_karyawan')->orWhere('status_karyawan', '!=', 'Resign');
                })
                ->get(['nama_karyawan', 'jabatan']);

            if ($subordinates->isNotEmpty()) {
                $prompt .= "Daftar Tim / Bawahan: " . $subordinates->map(fn ($s) => "{$s->nama_karyawan} ({$s->jabatan})")->implode(', ') . "\n";
            }

            $user = User::where('username', $karyawan->nik)->first();
            $annualLeave = $user ? $this->leaveAccrualService->getBalance($user) : 0;
            $phBal = $this->calculatePhBalance($user, $karyawan);
            $eoBal = $this->calculateExtraOffBalance($user, $karyawan);

            $prompt .= "Sisa Saldo Cuti Tahunan: {$annualLeave} hari\n";
            $prompt .= "Sisa Saldo PH (Public Holiday): {$phBal} hari\n";
            $prompt .= "Sisa Saldo Extra Off Aktif: {$eoBal} hari\n";

            // Rincian Histori Saldo Extra Off
            $eoRecords = EmployeeExtraOff::where('karyawan_nik', $karyawan->nik)->orderByDesc('periode_end')->get();
            if ($eoRecords->isNotEmpty()) {
                $prompt .= "\n[Riwayat Perolehan Extra Off Karyawan dari Payroll]\n";
                foreach ($eoRecords as $eo) {
                    $pStart = Carbon::parse($eo->periode_start)->format('d M Y');
                    $pEnd = Carbon::parse($eo->periode_end)->format('d M Y');
                    $expAt = Carbon::parse($eo->periode_end)->addMonths(3);
                    $isExp = now()->gt($expAt->endOfDay());
                    $expStr = $expAt->locale('id')->translatedFormat('d F Y');
                    $usedCount = $user ? \App\Models\ExtraOffRequest::where('user_id', $user->id)
                        ->whereDate('source_period_start', $eo->periode_start)
                        ->whereDate('source_period_end', $eo->periode_end)
                        ->whereNotIn('status', ['rejected', 'cancelled'])
                        ->count() : 0;
                    $statusKet = $isExp ? "KADALUWARSA (expired pada {$expStr})" : "Masih Berlaku s/d {$expStr}";
                    $prompt .= "- Periode {$pStart} - {$pEnd}: Dapat {$eo->days} hari (Terpakai: {$usedCount} hari, Status: {$statusKet})\n";
                }
            }

            // Rincian Pengajuan Libur / Cuti / PH Terakhir
            if ($user) {
                $eoReqs = \App\Models\ExtraOffRequest::where('user_id', $user->id)->latest()->take(5)->get();
                if ($eoReqs->isNotEmpty()) {
                    $prompt .= "\n[Riwayat Pengajuan Extra Off (EO) Karyawan]\n";
                    foreach ($eoReqs as $r) {
                        $tgl = Carbon::parse($r->claim_date)->locale('id')->translatedFormat('d F Y');
                        $prompt .= "- Tanggal Libur EO: {$tgl} (Status: {$r->status})\n";
                    }
                } else {
                    $prompt .= "\n[Riwayat Pengajuan Extra Off (EO) Karyawan]: Belum ada rekaman pengajuan Extra Off.\n";
                }

                $phReqs = \App\Models\PublicHolidayRequest::where('user_id', $user->id)->latest()->take(5)->get();
                if ($phReqs->isNotEmpty()) {
                    $prompt .= "\n[Riwayat Pengajuan Public Holiday (PH) Karyawan]\n";
                    foreach ($phReqs as $r) {
                        $tgl = Carbon::parse($r->claim_date)->locale('id')->translatedFormat('d F Y');
                        $prompt .= "- Tanggal Libur PH: {$tgl} (Status: {$r->status})\n";
                    }
                }
            }
        }

        return $prompt;
    }

    private function matchFaqDatabase(string $normalized): ?string
    {
        $faqs = \App\Models\WhatsappBotFaq::where('is_active', true)->get();
        foreach ($faqs as $faq) {
            $keywords = array_filter(array_map('trim', explode(',', strtolower($faq->keywords))));
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($normalized, $kw)) {
                    return $faq->answer;
                }
            }
        }

        return null;
    }

    private function findKaryawanByPayload(array $payload, string $sender): ?Karyawan
    {
        // 1. Cek nomor telepon asli (sender_phone atau phone)
        $rawPhone = (string) (data_get($payload, 'sender_phone') ?: data_get($payload, 'phone', $sender));
        $cleanPhone = preg_replace('/[^0-9]/', '', explode('@', $rawPhone)[0]);

        if ($cleanPhone !== '' && strlen($cleanPhone) >= 9 && strlen($cleanPhone) <= 15) {
            $phone08 = str_starts_with($cleanPhone, '62') ? '0' . substr($cleanPhone, 2) : $cleanPhone;
            $phone62 = str_starts_with($cleanPhone, '0') ? '62' . substr($cleanPhone, 1) : $cleanPhone;

            $karyawan = Karyawan::query()
                ->where(function ($q) use ($phone08, $phone62) {
                    $q->where('no_hp', $phone08)
                        ->orWhere('no_hp', $phone62)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(no_hp, '-', ''), ' ', ''), '+', '') IN (?, ?)", [$phone08, $phone62]);
                })
                ->first();

            if ($karyawan) {
                return $karyawan;
            }
        }

        // 2. Cadangan: Cocokkan PushName (Dibersihkan dari unicode / font khusus)
        $pushName = trim((string) data_get($payload, 'pushName', ''));
        if ($pushName !== '') {
            $cleanPushName = $this->cleanUnicodeName($pushName);
            if (strlen($cleanPushName) >= 3) {
                $karyawanByName = Karyawan::query()
                    ->where('nama_karyawan', 'LIKE', '%' . $cleanPushName . '%')
                    ->first();

                if ($karyawanByName) {
                    return $karyawanByName;
                }

                // Coba bagian kata pertama
                $firstWord = explode(' ', $cleanPushName)[0];
                if (strlen($firstWord) >= 3) {
                    return Karyawan::query()
                        ->where('nama_karyawan', 'LIKE', '%' . $firstWord . '%')
                        ->first();
                }
            }
        }

        return null;
    }

    private function cleanUnicodeName(string $name): string
    {
        $map = [
            'ₐ' => 'a', 'ᵦ' => 'b', '𝒸' => 'c', '𝒹' => 'd', 'ₑ' => 'e', '𝒻' => 'f', '₉' => 'g',
            'ₕ' => 'h', 'ᵢ' => 'i', 'ⱼ' => 'j', 'ₖ' => 'k', 'ₗ' => 'l', 'ₘ' => 'm', 'ₙ' => 'n',
            'ₒ' => 'o', 'ₚ' => 'p', 'ᵩ' => 'q', 'ᵣ' => 'r', 'ₛ' => 's', 'ₜ' => 't', 'ᵤ' => 'u',
            'ᵥ' => 'v', '𝓌' => 'w', 'ₓ' => 'x', 'ᵧ' => 'y', '𝓏' => 'z',
        ];
        $converted = strtr($name, $map);
        return trim(preg_replace('/[^a-zA-Z0-9\s]/', '', Str::ascii($converted)));
    }

    private function stripTrigger(string $message): ?string
    {
        $message = trim($message);
        $trigger = trim((string) config('services.hris_agent.trigger_prefix', ''));

        if ($trigger === '') {
            return $message;
        }

        $lowerMessage = Str::lower($message);
        $lowerTrigger = Str::lower($trigger);

        foreach ([$lowerTrigger, '@'.$lowerTrigger] as $prefix) {
            if (str_starts_with($lowerMessage, $prefix)) {
                return trim(substr($message, strlen($prefix)));
            }
        }

        return null;
    }

    private function senderAllowed(string $sender): bool
    {
        $allowed = config('services.hris_agent.allowed_senders', []);

        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        return in_array($sender, $allowed, true)
            || in_array(preg_replace('/[^0-9]/', '', $sender), $allowed, true);
    }

    private function extractSender(array $payload): string
    {
        return $this->firstString($payload, [
            'remoteJid',
            'from',
            'key.remoteJid',
            'data.remoteJid',
            'data.from',
            'phone',
            'sender',
            'jid',
            'chat',
            'data.phone',
            'data.sender',
            'data.jid',
            'data.chat',
            'data.key.remoteJid',
            'messages.0.key.remoteJid',
        ]);
    }

    private function extractMessage(array $payload): string
    {
        return $this->firstString($payload, [
            'message',
            'text',
            'body',
            'conversation',
            'content',
            'message.text',
            'message.body',
            'message.conversation',
            'message.extendedTextMessage.text',
            'data.message',
            'data.text',
            'data.body',
            'data.message.text',
            'data.message.body',
            'data.message.conversation',
            'data.message.extendedTextMessage.text',
            'messages.0.message.conversation',
            'messages.0.message.extendedTextMessage.text',
        ]);
    }

    private function firstString(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    private function isFromMe(array $payload): bool
    {
        foreach (['fromMe', 'isFromMe', 'key.fromMe', 'data.fromMe', 'data.key.fromMe', 'messages.0.key.fromMe'] as $path) {
            $value = data_get($payload, $path);

            if ($value === true || $value === 'true' || $value === 1 || $value === '1') {
                return true;
            }
        }

        return false;
    }

    private function sendReply(string $sender, string $message): bool
    {
        $botUrl = rtrim(trim((string) config('services.hris_agent.bot_url')), '/');
        if ($botUrl !== '') {
            try {
                $response = \Illuminate\Support\Facades\Http::timeout(10)->post($botUrl . '/send/message', [
                    'phone' => $sender,
                    'message' => $message,
                ]);
                if ($response->successful()) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal kirim via dedicated bot URL, fallback ke WhatsAppService', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->whatsApp->sendMessage($sender, $message);
    }

    private function calculatePhBalance(?User $user, ?Karyawan $karyawan): int
    {
        if (! $user || ! $karyawan) {
            return 0;
        }

        $attendedDates = $karyawan->pin
            ? \App\Models\FingerspotAttendanceLog::query()
                ->where('pin', $karyawan->pin)
                ->whereBetween('scan_date', [now()->subDays(90)->startOfDay(), now()->startOfDay()])
                ->get(['scan_date'])
                ->pluck('scan_date')
                ->map(fn ($date) => \Carbon\Carbon::parse($date)->toDateString())
                ->unique()
            : collect();

        $joinDate = $karyawan->join_date ? \Carbon\Carbon::parse($karyawan->join_date)->startOfDay() : null;

        $deductedHolidayIds = EmployeePhAdjustment::query()
            ->where('karyawan_nik', $karyawan->nik)
            ->whereNotNull('public_holiday_id')
            ->where('days', '<', 0)
            ->pluck('public_holiday_id');

        $eligibleHolidays = \App\Models\PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->when($joinDate, fn ($q) => $q->whereDate('holiday_date', '>=', $joinDate))
            ->whereNotIn('id', $deductedHolidayIds)
            ->orderByDesc('holiday_date')
            ->get()
            ->filter(fn ($h) => $h->holiday_date->lt(\Carbon\Carbon::parse('2025-01-01')) || $attendedDates->contains($h->holiday_date->toDateString()))
            ->values();

        $usedHolidayIds = PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->pluck('public_holiday_id');

        $generalAdjustmentDays = (int) EmployeePhAdjustment::query()
            ->where('karyawan_nik', $karyawan->nik)
            ->whereNull('public_holiday_id')
            ->sum('days');

        return max(0, $eligibleHolidays->whereNotIn('id', $usedHolidayIds)->count() + $generalAdjustmentDays);
    }

    private function calculateExtraOffBalance(?User $user, ?Karyawan $karyawan): int
    {
        if (! $user || ! $karyawan) {
            return 0;
        }

        $eoBalance = 0;
        $eoSources = EmployeeExtraOff::where('karyawan_nik', $karyawan->nik)->where('days', '>', 0)->get();
        foreach ($eoSources as $source) {
            $pEnd = $source->periode_end ? \Carbon\Carbon::parse($source->periode_end) : null;
            $expiredAt = $pEnd ? $pEnd->copy()->addMonths(3) : null;
            $isExpired = $expiredAt ? now()->gt($expiredAt->copy()->endOfDay()) : false;
            if (! $isExpired) {
                $used = \App\Models\ExtraOffRequest::where('user_id', $user->id)
                    ->whereDate('source_period_start', $source->periode_start)
                    ->whereDate('source_period_end', $source->periode_end)
                    ->whereNotIn('status', ['rejected', 'cancelled'])
                    ->count();
                $eoBalance += max(0, (int) $source->days - $used);
            }
        }

        return $eoBalance;
    }

    private function summarizeTicketIssue(string $currentQuestion, array $history = []): string
    {
        if (empty($history)) {
            return "\"{$currentQuestion}\"";
        }

        // Kumpulkan teks percakapan terakhir
        $convo = [];
        foreach ($history as $h) {
            $role = data_get($h, 'role') === 'user' ? 'Karyawan' : 'Bot';
            $txt = data_get($h, 'parts.0.text', data_get($h, 'content', ''));
            if ($txt !== '') {
                $convo[] = "{$role}: {$txt}";
            }
        }
        $convo[] = "Karyawan: {$currentQuestion}";
        $convoText = implode("\n", $convo);

        $prompt = "Berikut adalah riwayat percakapan terakhir antara karyawan dan bot WhatsApp kantor:\n\n";
        $prompt .= "{$convoText}\n\n";
        $prompt .= "Tugas: Buat 1 atau 2 kalimat singkat yang merangkum secara jelas inti topik/kendala apa yang sebenarnya sedang dihadapi atau ditanyakan oleh karyawan untuk dibaca oleh Tim IT Support di grup tiket.\n";
        $prompt .= "Aturan: Tulis langsung inti kendalanya dengan bahasa Indonesia yang jelas tanpa basa-basi.";

        $summary = $this->openRouter->chat($prompt, '') ?? $this->gemini->chat($prompt, '');

        if ($summary && trim($summary) !== '') {
            return trim($summary);
        }

        // Fallback jika AI tidak merespon: ambil pertanyaan user sebelumnya
        $previousUserMsg = '';
        foreach (array_reverse($history) as $h) {
            if (data_get($h, 'role') === 'user') {
                $previousUserMsg = data_get($h, 'parts.0.text', '');
                break;
            }
        }

        return $previousUserMsg ? "\"{$previousUserMsg}\"\n_(Permintaan eskalasi: {$currentQuestion})_" : "\"{$currentQuestion}\"";
    }
}
