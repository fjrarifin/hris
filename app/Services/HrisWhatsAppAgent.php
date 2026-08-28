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
        private readonly OpenRouterChatService $openRouter,
        private readonly GeminiChatService $gemini,
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

        // 1. Cek apakah ada aksi transaksional / data pasti yang ditanyakan (Instan)
        $actionAnswer = $this->handleDirectActions($normalized, $karyawan, $history);
        if ($actionAnswer !== null) {
            return $actionAnswer;
        }

        // 2. Cek database FAQ / Knowledge Base dinamis
        $faqAnswer = $this->matchFaqDatabase($normalized);
        if ($faqAnswer !== null) {
            return $faqAnswer;
        }

        // 3. Cek template jawaban umum / salam
        $fixed = $this->fixedAnswer($normalized, $karyawan, $isFirstChat);
        if ($fixed !== null) {
            return $fixed;
        }

        // 4. Gunakan AI Database Query Agent (Autonomous Text-to-SQL Read-Only)
        $dbAgentAnswer = $this->dbQueryAgent->queryAndAnswer($question, $karyawan, $history);
        if ($dbAgentAnswer !== null && trim($dbAgentAnswer) !== '') {
            return $dbAgentAnswer;
        }

        // 5. Fallback ke LLM General Knowledge: OpenRouter (DeepSeek/Llama) lalu Gemini Flash
        $systemPrompt = $this->buildSystemPrompt($karyawan, $isFirstChat);
        
        $openRouterResponse = $this->openRouter->chat($question, $systemPrompt, $history);
        if ($openRouterResponse !== null && $openRouterResponse !== '') {
            return $openRouterResponse;
        }

        $geminiResponse = $this->gemini->chat($question, $systemPrompt, $history);
        if ($geminiResponse !== null && $geminiResponse !== '') {
            return $geminiResponse;
        }

        return "Maaf ya, aku hanya bisa membantu menjawab pertanyaan seputar HRIS, absensi, jadwal kerja, saldo cuti, dan informasi operasional kantor ya.";
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

            return "Siap {$sapaan}! Pesan dan permintaan bantuanmu sudah aku teruskan langsung ke Grup Tim IT Support ya 📩.\n\nTim IT akan segera mengecek dan menghubungimu melalui WhatsApp ini. Mohon ditunggu sebentar ya {$sapaan}!";
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
        // Aksi 1.2: Cek NIK Karyawan
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'nik')
            && (str_contains($normalized, 'saya') || str_contains($normalized, 'berapa') || str_contains($normalized, 'cek') || str_contains($normalized, 'apa'))
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di sistem HRIS nih. Boleh hubungi HRD ya.";
            }

            return "NIK kamu adalah *{$karyawan->nik}* ya.";
        }

        // -------------------------------------------------------------
        // Aksi 2: Cek Saldo PH (Public Holiday)
        // -------------------------------------------------------------
        $isAskingProcedure = str_contains($normalized, 'cara') || str_contains($normalized, 'gimana') || str_contains($normalized, 'bagaimana') || str_contains($normalized, 'alur') || str_contains($normalized, 'syarat');
        if (
            ! $isAskingProcedure
            && (str_contains($normalized, 'saldo ph')
                || str_contains($normalized, 'sisa ph')
                || str_contains($normalized, 'public holiday')
                || (str_contains($normalized, 'ph') && (str_contains($normalized, 'sisa') || str_contains($normalized, 'saldo') || str_contains($normalized, 'berapa'))))
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di HRIS nih. Coba hubungi HRD ya.";
            }

            $user = User::where('username', $karyawan->nik)->first();
            $phBalance = $this->calculatePhBalance($user, $karyawan);

            return "Untuk saldo PH (Public Holiday) saat ini ada *{$phBalance} hari* ya.";
        }

        // -------------------------------------------------------------
        // Aksi 3: Cek Saldo Cuti Tahunan (Khusus jika tanya Cuti)
        // -------------------------------------------------------------
        if (
            ! $isAskingProcedure
            && (str_contains($normalized, 'cuti') || str_contains($normalized, 'tahunan'))
            && ! str_contains($normalized, 'ph')
            && ! str_contains($normalized, 'extra off')
            && ! str_contains($normalized, 'eo')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di HRIS nih. Coba hubungi HRD ya.";
            }

            $user = User::where('username', $karyawan->nik)->first();
            $annualLeaveBalance = $user ? $this->leaveAccrualService->getBalance($user) : 0;
            
            $activeContract = DB::table('t_kontrak_karyawan')
                ->where('nik', $karyawan->nik)
                ->orderByDesc('end_date')
                ->first();
            $leaveExpiredAt = $activeContract ? Carbon::parse($activeContract->end_date)->locale('id')->translatedFormat('d F Y') : null;

            $expiredInfo = $leaveExpiredAt ? " (berlaku s/d {$leaveExpiredAt})" : "";
            return "Sisa cuti tahunan kamu saat ini ada *{$annualLeaveBalance} hari*{$expiredInfo} ya.";
        }

        // -------------------------------------------------------------
        // Aksi 4: Cek Saldo Extra Off (Khusus jika tanya EO)
        // -------------------------------------------------------------
        if (
            ! $isAskingProcedure
            && (str_contains($normalized, 'extra off') || str_contains($normalized, 'eo'))
            && ! str_contains($normalized, 'cuti')
            && ! str_contains($normalized, 'ph')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di HRIS nih. Coba hubungi HRD ya.";
            }

            $user = User::where('username', $karyawan->nik)->first();
            $eoBalance = $this->calculateExtraOffBalance($user, $karyawan);

            return "Saldo Extra Off kamu saat ini ada *{$eoBalance} hari* ya.";
        }

        // -------------------------------------------------------------
        // Aksi 5: Cek Semua Saldo (Cuti, PH, Extra Off bersamaan)
        // -------------------------------------------------------------
        if (
            ! $isAskingProcedure
            && (str_contains($normalized, 'saldo')
                || str_contains($normalized, 'sisa libur')
                || str_contains($normalized, 'rekap cuti'))
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di HRIS nih. Coba hubungi HRD ya.";
            }

            $user = User::where('username', $karyawan->nik)->first();
            $annualLeaveBalance = $user ? $this->leaveAccrualService->getBalance($user) : 0;
            
            $activeContract = DB::table('t_kontrak_karyawan')
                ->where('nik', $karyawan->nik)
                ->orderByDesc('end_date')
                ->first();
            $leaveExpiredAt = $activeContract ? Carbon::parse($activeContract->end_date)->locale('id')->translatedFormat('d F Y') : null;

            $phBalance = $this->calculatePhBalance($user, $karyawan);
            $eoBalance = $this->calculateExtraOffBalance($user, $karyawan);

            $msg = "Berikut rincian saldo kamu ya {$sapaan}:\n";
            $msg .= "• Sisa Cuti Tahunan: *{$annualLeaveBalance} hari*" . ($leaveExpiredAt ? " (s/d {$leaveExpiredAt})" : "") . "\n";
            $msg .= "• Saldo PH (Public Holiday): *{$phBalance} hari*\n";
            $msg .= "• Saldo Extra Off: *{$eoBalance} hari*";

            return trim($msg);
        }

        // -------------------------------------------------------------
        // Aksi 6: Cek Masa Berlaku Kontrak Kerja
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'kontrak')
            || str_contains($normalized, 'habis kontrak')
            || str_contains($normalized, 'akhir kontrak')
            || str_contains($normalized, 'selesai kontrak')
            || str_contains($normalized, 'masa kerja')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di sistem HRIS nih.";
            }

            $activeContract = DB::table('t_kontrak_karyawan')
                ->where('nik', $karyawan->nik)
                ->orderByDesc('end_date')
                ->first();

            if (! $activeContract) {
                $statusKaryawan = $karyawan->status_karyawan ?: 'Tetap / PKWTT';
                return "Status kamu saat ini tercatat *{$statusKaryawan}* dan tidak ada masa kontrak berkala (PKWT) aktif ya.";
            }

            $endDate = Carbon::parse($activeContract->end_date)->locale('id')->translatedFormat('d F Y');
            $daysLeft = (int) now()->diffInDays(Carbon::parse($activeContract->end_date), false);

            if ($daysLeft >= 0) {
                return "Kontrak kerja kamu saat ini aktif sampai tanggal *{$endDate}* (kurang lebih sisa {$daysLeft} hari lagi) ya.";
            }

            return "Kontrak kerja kamu tercatat sudah selesai pada tanggal *{$endDate}*. Silakan konfirmasi ke atasan atau HRD ya.";
        }

        // -------------------------------------------------------------
        // Aksi 7: Cek Presensi & Log Scan Fingerspot (Hari Ini)
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'scan')
            || str_contains($normalized, 'absen')
            || str_contains($normalized, 'presensi')
            || str_contains($normalized, 'tadi masuk')
            || str_contains($normalized, 'sudah masuk')
            || str_contains($normalized, 'sudah pulang')
            || str_contains($normalized, 'kehadiran')
            || str_contains($normalized, 'masuk tidak hari ini')
            || str_contains($normalized, 'masuk ga hari ini')
            || str_contains($normalized, 'tadi scan')
            || str_contains($normalized, 'scan tadi')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di sistem HRIS nih.";
            }

            if (! $karyawan->pin) {
                return "PIN mesin absensi kamu belum terdaftar di profil HRIS. Boleh konfirmasi ke tim HRD ya.";
            }

            $today = Carbon::today()->toDateString();
            $todayFormatted = Carbon::today()->locale('id')->translatedFormat('l, d F Y');

            $logsToday = FingerspotAttendanceLog::where('pin', $karyawan->pin)
                ->whereDate('scan_date', $today)
                ->orderBy('scan_date')
                ->get();

            if ($logsToday->isEmpty()) {
                $logsToday = DB::table('fingerspot_attendance_logs_fed')
                    ->where('pin', $karyawan->pin)
                    ->whereDate('scan_date', $today)
                    ->orderBy('scan_date')
                    ->get();
            }

            if ($logsToday->isEmpty()) {
                // Cek apakah ada koreksi absensi yang disetujui hari ini
                $correction = DB::table('attendance_corrections')
                    ->where('karyawan_nik', $karyawan->nik)
                    ->where('correction_date', $today)
                    ->where('status', 'approved')
                    ->first();

                if ($correction) {
                    $in = $correction->corrected_scan_in ? substr($correction->corrected_scan_in, 0, 5) . ' WIB' : '-';
                    $out = $correction->corrected_scan_out ? substr($correction->corrected_scan_out, 0, 5) . ' WIB' : '-';
                    return "Presensi kamu hari ini ({$todayFormatted}) tercatat via *Koreksi Absen (Approved)*:\n• Scan Masuk: *{$in}*\n• Scan Pulang: *{$out}*";
                }

                return "Untuk hari ini ({$todayFormatted}), belum ada rekaman scan absensi di mesin fingerspot atas nama kamu ya.";
            }

            $scanIn = $logsToday->first(fn ($l) => (string) $l->status_scan === '0') ?? $logsToday->first();
            $scanOut = $logsToday->reverse()->first(fn ($l) => (string) $l->status_scan === '1') ?? ($logsToday->count() > 1 ? $logsToday->last() : null);

            $jamMasuk = $scanIn ? Carbon::parse($scanIn->scan_date)->format('H:i') . ' WIB' : '-';
            $jamPulang = $scanOut && $scanOut->id !== $scanIn->id ? Carbon::parse($scanOut->scan_date)->format('H:i') . ' WIB' : 'Belum scan pulang';

            $msg = "Presensi kamu hari ini ({$todayFormatted}):\n";
            $msg .= "• Scan Masuk: *{$jamMasuk}*\n";
            $msg .= "• Scan Pulang: *{$jamPulang}*";

            return $msg;
        }

        // -------------------------------------------------------------
        // Aksi 7.5: Cek Jadwal Kerja / Shift (Hari ini & Besok)
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'jadwal')
            || str_contains($normalized, 'shift')
            || str_contains($normalized, 'masuk apa')
            || str_contains($normalized, 'masuk jam')
            || str_contains($normalized, 'kerja apa')
            || str_contains($normalized, 'jam masuk')
            || str_contains($normalized, 'besok libur')
            || str_contains($normalized, 'besok kerja')
            || str_contains($normalized, 'hari ini libur')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di HRIS nih.";
            }

            $isBesok = str_contains($normalized, 'besok');
            $targetDate = $isBesok ? Carbon::tomorrow() : Carbon::today();
            $labelHari = $isBesok ? 'Besok' : 'Hari ini';
            $dateStr = $targetDate->toDateString();
            $dateFormatted = $targetDate->locale('id')->translatedFormat('l, d F Y');

            $schedule = EmployeeDailySchedule::with('category')
                ->where('karyawan_nik', $karyawan->nik)
                ->where('schedule_date', $dateStr)
                ->first();

            if (! $schedule || ! $schedule->category) {
                return "Untuk {$labelHari} ({$dateFormatted}), kamu masuk *Shift Reguler (08:30 - 17:30 WIB)* ya.";
            }

            $category = $schedule->category;
            $isOff = $category->is_off || strtolower($category->name) === 'off' || strtolower($category->name) === 'libur';

            if ($isOff) {
                return "Untuk {$labelHari} ({$dateFormatted}), kamu *Libur (OFF)* ya. Selamat beristirahat!";
            }

            $in = substr($category->start_time ?? '08:30:00', 0, 5);
            $out = substr($category->end_time ?? '17:30:00', 0, 5);
            return "Untuk {$labelHari} ({$dateFormatted}), kamu terjadwal shift *{$category->name}* (masuk jam *{$in} - {$out} WIB*) ya.";
        }

        // -------------------------------------------------------------
        // Aksi 8: Lupa / Reset Password (Langsung reset ke default 12345678)
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

        // -------------------------------------------------------------
        // Aksi 9: Cek Bawahan / Anggota Tim
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'bawahan')
            || str_contains($normalized, 'anggota tim')
            || str_contains($normalized, 'tim saya')
            || str_contains($normalized, 'staff saya')
            || str_contains($normalized, 'anak buah')
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di sistem HRIS nih.";
            }

            $subordinates = Karyawan::query()
                ->where(function ($q) use ($karyawan) {
                    $q->where('atasan_langsung_nik', $karyawan->nik)
                        ->orWhere('atasan_tidak_langsung_nik', $karyawan->nik);
                })
                ->where(function ($q) {
                    $q->whereNull('status_karyawan')->orWhere('status_karyawan', '!=', 'Resign');
                })
                ->get(['nik', 'nama_karyawan', 'jabatan', 'departement']);

            if ($subordinates->isEmpty()) {
                return "Saat ini tidak ada data bawahan langsung yang tercatat di bawah NIK kamu ya.";
            }

            $msg = "Berikut daftar anggota tim/bawahan kamu:\n";
            foreach ($subordinates as $i => $sub) {
                $jabatan = $sub->jabatan ?: $sub->departement ?: '-';
                $msg .= ($i + 1) . ". *{$sub->nama_karyawan}* ({$jabatan})\n";
            }
            return trim($msg);
        }

        // -------------------------------------------------------------
        // Aksi 10: Cek Atasan Langsung
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'atasan')
            && (str_contains($normalized, 'saya') || str_contains($normalized, 'siapa') || str_contains($normalized, 'langsung'))
        ) {
            if (! $karyawan) {
                return "Nomor WhatsApp ini belum terdaftar di sistem HRIS nih.";
            }

            $atasan = $karyawan->atasanLangsung;
            if (! $atasan) {
                return "Data atasan langsung belum tercatat di profil HRIS kamu. Boleh konfirmasi ke HRD ya.";
            }

            $jabatan = $atasan->jabatan ? " ({$atasan->jabatan})" : "";
            return "Atasan langsung kamu adalah *{$atasan->nama_karyawan}*{$jabatan} ya.";
        }

        // -------------------------------------------------------------
        // Aksi 11: Prosedur Pengajuan Cuti / PH / EO / Lembur
        // -------------------------------------------------------------
        if (
            str_contains($normalized, 'cara')
            || str_contains($normalized, 'alur')
            || str_contains($normalized, 'gimana')
            || str_contains($normalized, 'bagaimana')
            || str_contains($normalized, 'pengajuan')
            || str_contains($normalized, 'mengajukan')
        ) {
            $contextText = $normalized;
            if (! empty($history)) {
                foreach ($history as $h) {
                    $contextText .= ' ' . strtolower(data_get($h, 'parts.0.text', ''));
                }
            }

            if (str_contains($contextText, 'ph') || str_contains($contextText, 'public holiday')) {
                return "Untuk pengajuan PH (Public Holiday):\n1. Buka aplikasi Mobile HRIS atau Web Portal (https://hr.hompimplay.id)\n2. Masuk ke menu *Pengajuan PH*\n3. Pilih tanggal libur PH yang dikerjakan / tanggal pengganti cuti\n4. Klik *Kirim Pengajuan* untuk diteruskan ke atasan kamu.";
            }

            if (str_contains($contextText, 'cuti') || str_contains($contextText, 'izin') || str_contains($contextText, 'sakit')) {
                return "Untuk pengajuan Cuti / Izin / Sakit:\n1. Buka aplikasi Mobile HRIS atau Web (https://hr.hompimplay.id)\n2. Masuk ke menu *Pengajuan Cuti / Izin*\n3. Pilih jenis pengajuan, tanggal mulai s/d selesai, dan tulis alasan\n4. Lampirkan surat dokter jika sakit > 1 hari, lalu klik *Ajukan*.";
            }

            if (str_contains($contextText, 'extra off') || str_contains($contextText, 'eo')) {
                return "Untuk pengajuan Extra Off (EO):\n1. Buka menu *Pengajuan Extra Off* di aplikasi HRIS\n2. Pilih tanggal pengambilan libur EO kamu\n3. Klik *Kirim Pengajuan* untuk mendapatkan persetujuan atasan.";
            }

            if (str_contains($contextText, 'lembur') || str_contains($contextText, 'spl')) {
                return "Untuk pengajuan Lembur (SPL):\n1. Buka menu *Pengajuan Lembur* di HRIS\n2. Isi tanggal, jam mulai & selesai lembur, serta deskripsi pekerjaan\n3. Klik *Ajukan*.";
            }
        }

        return null;
    }

    private function fixedAnswer(string $question, ?Karyawan $karyawan, bool $isFirstChat = false): ?string
    {
        $normalized = Str::of($question)
            ->lower()
            ->replaceMatches('/[^a-z0-9\s]/', ' ')
            ->squish()
            ->toString();

        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";

        if (in_array($normalized, ['help', 'bantuan', 'menu', 'hai', 'halo', 'p', 'tes', 'test', 'siang', 'pagi', 'malam', 'sore', 'halo kak', 'hai kak', 'pagi kak', 'siang kak', 'sore kak', 'malam kak'], true)) {
            if ($isFirstChat) {
                $msg = "Halo {$sapaan}! Aku IT AI Agent HRIS kantor.\n\n";
                $msg .= "Ada yang bisa aku bantu seputar HRIS atau kebutuhan kantor hari ini?\n\n";
                $msg .= "Kamu bisa tanyakan hal-hal seperti:\n";
                $msg .= "• Sisa cuti / PH / Extra Off\n";
                $msg .= "• Jadwal masuk kerja & presensi scan absensi\n";
                $msg .= "• Sisa masa kontrak kerja\n";
                $msg .= "• Reset password akun HRIS\n";
                $msg .= "• Prosedur pengajuan atau pertanyaan seputar kantor lainnya.";

                return $msg;
            }

            return "Ada lagi yang bisa aku bantu seputar HRIS atau kantor, {$sapaan}?";
        }

        if (str_contains($normalized, 'apa itu hris') || str_contains($normalized, 'hris itu apa')) {
            return "HRIS adalah aplikasi internal kantor untuk presensi, cek jadwal, pengajuan cuti/izin/lembur, dan slip gaji ya {$sapaan}.";
        }

        return null;
    }

    private function buildSystemPrompt(?Karyawan $karyawan, bool $isFirstChat = false): string
    {
        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";

        $prompt = "Kamu adalah IT AI Agent HRIS (asisten AI resmi kantor) yang bertugas membantu melayani chat WhatsApp dari rekan karyawan.\n";
        $prompt .= "Aturan Percakapan WhatsApp:\n";
        $prompt .= "1. Selalu panggil karyawan dengan sebutan: '{$sapaan}' agar sopan, ramah, dan bersahabat.\n";
        if ($isFirstChat) {
            $prompt .= "2. Ini adalah CHAT PERTAMA dari karyawan. Kamu boleh membuka dengan sapaan dan perkenalkan dirimu secara singkat sebagai IT AI Agent HRIS kantor (contoh: 'Halo {$sapaan}! Aku IT AI Agent kantor. Ada yang bisa dibantu?').\n";
        } else {
            $prompt .= "2. Percakapan ini SEDANG BERJALAN (bukan chat pertama). DILARANG MENGULANG KATA 'HALO' / 'HALO KAK' di awal kalimat balasan. Langsung berikan jawaban to-the-point dengan santai dan panggil {$sapaan}.\n";
        }
        $prompt .= "3. Jangan gunakan format formal kaku seperti surat dan jangan menggunakan emoji yang berlebihan.\n";
        $prompt .= "4. Jawab santun, to-the-point, ringkas, dan membantu.\n";
        $prompt .= "5. Jika karyawan mengeluh kesulitan login web karena sesi aktif di browser lain, sarankan mereka ketik 'reset session' atau balas 'iya' agar sistem langsung membebaskan sesinya.\n";
        $prompt .= "6. PENTING: JANGAN PERNAH mengatakan 'sebentar ya aku cek dulu / nanti aku kabari lagi' karena chat ini dijawab seketika secara real-time. Berikan jawaban tuntas langsung.\n";
        $prompt .= "7. Password default portal HRIS adalah 12345678 (delapan digit: 12345678, BUKAN 123456). Jika karyawan meminta reset password, kamu bisa infokan bahwa password mereka dapat/sudah di-reset ke 12345678.\n";
        $prompt .= "8. BATASAN TOPIK HRIS (SANGAT PENTING): Kamu HANYA boleh menjawab pertanyaan seputar HRIS, presensi/kehadiran, jadwal kerja, cuti, izin, lembur, info kontrak, slip gaji, dan kebijakan operasional kantor.\n";
        $prompt .= "JIKA karyawan menanyakan hal di luar topik HRIS/kantor (misalnya resep makanan, tugas sekolah/kuliah, ramalan cuaca, lelucon umum, politik, sains/coding umum di luar sistem), KAMU WAJIB MENOLAK secara santun dengan jawaban:\n";
        $prompt .= "\"Maaf ya {$sapaan}, aku hanya bisa membantu menjawab pertanyaan seputar HRIS, absensi, jadwal kerja, saldo cuti, dan informasi operasional kantor ya.\"\n";

        if ($karyawan) {
            $prompt .= "\n[Info Karyawan yang Bertanya]\n";
            $prompt .= "Nama Lengkap: {$karyawan->nama_karyawan}\n";
            $prompt .= "NIK: {$karyawan->nik}\n";
            $prompt .= "Jabatan: " . ($karyawan->jabatan ?: $karyawan->posisi ?: '-') . "\n";
            $prompt .= "Divisi: " . ($karyawan->departement ?: $karyawan->divisi ?: '-') . "\n";

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
                $prompt .= "Daftar Bawahan Langsung: " . $subordinates->map(fn ($s) => "{$s->nama_karyawan} ({$s->jabatan})")->implode(', ') . "\n";
            }

            $user = User::where('username', $karyawan->nik)->first();
            $annualLeave = $user ? $this->leaveAccrualService->getBalance($user) : 0;
            $phBal = $this->calculatePhBalance($user, $karyawan);
            $eoBal = $this->calculateExtraOffBalance($user, $karyawan);

            $prompt .= "Sisa Saldo Cuti Tahunan: {$annualLeave} hari\n";
            $prompt .= "Sisa Saldo PH (Public Holiday): {$phBal} hari\n";
            $prompt .= "Sisa Saldo Extra Off: {$eoBal} hari\n";
        }

        // Tambahkan Knowledge Base FAQ dari Database
        $faqs = \App\Models\WhatsappBotFaq::where('is_active', true)->get();
        if ($faqs->isNotEmpty()) {
            $prompt .= "\n[Knowledge Base / Informasi Kebijakan Kantor]\n";
            foreach ($faqs as $faq) {
                $prompt .= "- {$faq->topic}: {$faq->answer}\n";
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
