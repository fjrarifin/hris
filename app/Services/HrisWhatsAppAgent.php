<?php

namespace App\Services;

use App\Http\Controllers\LeaveAccrualService;
use App\Http\Services\WhatsAppService;
use App\Models\EmployeeDailySchedule;
use App\Models\EmployeeExtraOff;
use App\Models\EmployeePhAdjustment;
use App\Models\FingerspotAttendanceLog;
use App\Models\GateQrUsageLog;
use App\Models\Karyawan;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Notifications\GateQrUsageNotification;
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
        $userLevel = $this->resolveUserLevel($karyawan, $sender);

        $historyKey = 'wa_ai_agent_history:' . $cleanSenderKey;
        $history = Cache::get($historyKey, []);

        $answer = $this->answer($question, $karyawan, $sender, $history, $userLevel);
        if ($answer === '__IMAGE_SENT__') {
            $sent = true;
            $historyAnswer = "✅ [Gambar QR Gate Turnstile berhasil dibuat dan dikirim ke WhatsApp]";
        } else {
            $sent = $this->sendReply($sender, $answer);
            $historyAnswer = $answer;
        }

        // Simpan riwayat percakapan (Multi-turn Context) maksimal 6 percakapan terakhir
        $history[] = ['role' => 'user', 'parts' => [['text' => $question]]];
        $history[] = ['role' => 'model', 'parts' => [['text' => $historyAnswer]]];
        if (count($history) > 6) {
            $history = array_slice($history, -6);
        }
        Cache::put($historyKey, $history, now()->addMinutes(30));

        return [
            'status' => $sent ? 'sent' : 'send_failed',
            'sender' => $sender,
            'karyawan_nik' => $karyawan?->nik,
            'user_level' => $userLevel,
            'question' => $question,
        ];
    }

    private function answer(string $question, ?Karyawan $karyawan, string $sender, array $history = [], int $userLevel = 3): string
    {
        $normalized = $this->normalizeText($question);
        $isFirstChat = empty($history);

        // 1. Cek apakah ada aksi transaksional khusus (Eskalasi IT, Reset Session, Reset Password, Smart Gate QR)
        $actionAnswer = $this->handleDirectActions($question, $normalized, $karyawan, $sender, $history);
        if ($actionAnswer !== null) {
            return $actionAnswer;
        }

        // 2. Cek database FAQ / Knowledge Base dinamis
        $faqAnswer = $this->matchFaqDatabase($normalized);
        if ($faqAnswer !== null) {
            return $faqAnswer;
        }

        // 3. Gunakan AI Database Query Agent (Autonomous Text-to-SQL + Natural Human Language + RBAC)
        $dbAgentAnswer = $this->dbQueryAgent->queryAndAnswer($question, $karyawan, $history, $userLevel);
        if ($dbAgentAnswer !== null && trim($dbAgentAnswer) !== '') {
            return $dbAgentAnswer;
        }

        // 4. Fallback ke LLM General Knowledge & Sapaan Alami (Gemini / OpenRouter)
        $systemPrompt = $this->buildSystemPrompt($karyawan, $isFirstChat, $userLevel);
        
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

    private function handleDirectActions(string $rawQuestion, string $normalized, ?Karyawan $karyawan, string $sender, array $history = []): ?string
    {
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
            $kendalaText = $this->summarizeTicketIssue($rawQuestion, $history);

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

        // -------------------------------------------------------------
        // Aksi 3: Multi-turn Gate QR Generation & Pengiriman Ulang QR Hari Ini
        // -------------------------------------------------------------
        $cleanSenderDigits = preg_replace('/[^0-9]/', '', $sender);
        $cacheQrKeyPhone = "wa_agent_pending_qr_phone:" . $cleanSenderDigits;
        $cacheQrKeyNik = $karyawan ? "wa_agent_pending_qr_nik:" . $karyawan->nik : null;

        $isAwaitingQrReason = (\Illuminate\Support\Facades\Cache::get($cacheQrKeyPhone) === 'awaiting_reason')
            || ($cacheQrKeyNik && \Illuminate\Support\Facades\Cache::get($cacheQrKeyNik) === 'awaiting_reason');

        // Deteksi apakah pesan meminta kirim ulang QR hari ini
        $isResendQrRequest = (
            str_contains($normalized, 'kirim qr')
            || str_contains($normalized, 'kirim ulang qr')
            || str_contains($normalized, 'kirimkan qr')
            || str_contains($normalized, 'kirim barcode')
            || str_contains($normalized, 'kirim ke saya qr')
            || str_contains($normalized, 'kirim melalui wa')
            || str_contains($normalized, 'kirim melalui whatsapp')
            || str_contains($normalized, 'kirim lewat wa')
            || str_contains($normalized, 'resend qr')
            || str_contains($normalized, 'mana qr')
            || str_contains($normalized, 'qr hari ini')
            || str_contains($normalized, 'qr code hari ini')
            || str_contains($normalized, 'barcode hari ini')
            || str_contains($normalized, 'qr code saya hari ini')
            || (str_contains($normalized, 'kirim') && (str_contains($normalized, 'wa') || str_contains($normalized, 'whatsapp')))
        );

        // Deteksi apakah pesan adalah permintaan QR Gate / Barcode Gate / Akses Masuk
        $isGateQrRequest = str_contains($normalized, 'qr gate')
            || str_contains($normalized, 'barcode gate')
            || str_contains($normalized, 'generate qr')
            || str_contains($normalized, 'bikin qr')
            || str_contains($normalized, 'buat qr')
            || str_contains($normalized, 'buatin saya qr')
            || str_contains($normalized, 'buatin qr')
            || str_contains($normalized, 'minta qr')
            || str_contains($normalized, 'mau qr')
            || str_contains($normalized, 'butuh qr')
            || str_contains($normalized, 'perlu qr')
            || str_contains($normalized, 'cetak qr')
            || str_contains($normalized, 'bisa minta qr')
            || str_contains($normalized, 'boleh minta qr')
            || str_contains($normalized, 'boleh qr')
            || str_contains($normalized, 'qr code')
            || str_contains($normalized, 'qr masuk')
            || str_contains($normalized, 'qr kantor')
            || str_contains($normalized, 'qr office')
            || str_contains($normalized, 'qr turnstile')
            || str_contains($normalized, 'minta barcode')
            || str_contains($normalized, 'mau barcode')
            || str_contains($normalized, 'cetak barcode')
            || str_contains($normalized, 'kartu tertinggal')
            || str_contains($normalized, 'kartu ketinggalan')
            || str_contains($normalized, 'lupa bawa kartu')
            || str_contains($normalized, 'tidak bawa kartu')
            || str_contains($normalized, 'kartu hilang')
            || str_contains($normalized, 'kartu rusak')
            || str_contains($normalized, 'akses gate')
            || str_contains($normalized, 'buka gate')
            || str_contains($normalized, 'masuk gate')
            || str_contains($normalized, 'scan gate')
            || (str_contains($normalized, 'qr') && (str_contains($normalized, 'hari ini') || str_contains($normalized, 'akses') || str_contains($normalized, 'masuk') || str_contains($normalized, 'gate') || str_contains($normalized, 'turnstile') || str_contains($normalized, 'office') || str_contains($normalized, 'kantor') || str_contains($normalized, 'boleh') || str_contains($normalized, 'bisa')));

        // Kecualikan jika hanya pertanyaan definisi umum
        if ($isGateQrRequest && (str_contains($normalized, 'apa itu qr') || str_contains($normalized, 'jelaskan qr'))) {
            $isGateQrRequest = false;
        }

        // Cek apakah pesan sudah langsung menyertakan alasan (1-shot generation)
        $hasImmediateReason = false;
        $extractedReason = '';
        $reasonKeywords = [
            'kartu tertinggal' => 'Kartu RFID tertinggal di rumah',
            'kartu ketinggalan' => 'Kartu RFID ketinggalan',
            'tertinggal di rumah' => 'Kartu RFID tertinggal di rumah',
            'ketinggalan di rumah' => 'Kartu RFID ketinggalan di rumah',
            'tertinggal' => 'Kartu RFID tertinggal',
            'ketinggalan' => 'Kartu RFID ketinggalan',
            'lupa bawa' => 'Lupa membawa kartu RFID',
            'tidak bawa kartu' => 'Tidak membawa kartu RFID',
            'kartu hilang' => 'Kartu RFID hilang',
            'hilang' => 'Kartu RFID hilang',
            'kartu rusak' => 'Kartu RFID rusak / tidak terbaca',
            'rusak' => 'Kartu RFID rusak / tidak terbaca',
            'belum dapat kartu' => 'Karyawan baru belum menerima kartu RFID',
            'kartu belum jadi' => 'Kartu RFID masih dalam proses cetak',
        ];

        foreach ($reasonKeywords as $kw => $defaultReason) {
            if (str_contains($normalized, $kw)) {
                $hasImmediateReason = true;
                $extractedReason = $defaultReason;
                break;
            }
        }

        // Helper untuk membersihkan cache SOP reason
        $clearPendingQrCache = function () use ($cacheQrKeyPhone, $cacheQrKeyNik) {
            \Illuminate\Support\Facades\Cache::forget($cacheQrKeyPhone);
            if ($cacheQrKeyNik) {
                \Illuminate\Support\Facades\Cache::forget($cacheQrKeyNik);
            }
        };

        // Jika nomor karyawan belum terdaftar di sistem HRIS
        if (($isGateQrRequest || $isResendQrRequest || $isAwaitingQrReason) && ! $karyawan) {
            $clearPendingQrCache();
            return "Maaf ya Kak, nomor WhatsApp ini belum terdaftar pada data karyawan HRIS HomPimPlay.\n\nSilakan hubungi Tim HRD untuk memperbarui nomor WhatsApp kamu di sistem agar bisa langsung membuat dan mencetak QR Gate via WhatsApp ya.";
        }

        // Jika karyawan sedang menjawab pertanyaan SOP alasan
        if ($isAwaitingQrReason) {
            $clearPendingQrCache();
            $reason = trim($rawQuestion);
            if (strlen($reason) < 3) {
                $reason = "Akses masuk kantor (Permintaan via WhatsApp)";
            }
            return $this->processGateQrGeneration($karyawan, $sender, $reason, true);
        }

        // Jika karyawan langsung menyertakan alasan di pesan pertama
        if ($isGateQrRequest && $hasImmediateReason && $karyawan) {
            $clearPendingQrCache();
            return $this->processGateQrGeneration($karyawan, $sender, $extractedReason, true);
        }

        // Jika karyawan meminta kirim ulang QR yang sudah pernah dibuat hari ini
        if ($isResendQrRequest && $karyawan) {
            $todayLog = GateQrUsageLog::where('nik', $karyawan->nik)
                ->whereDate('used_at', now()->toDateString())
                ->latest('id')
                ->first();

            if ($todayLog) {
                return $this->processGateQrGeneration($karyawan, $sender, $todayLog->reason, false);
            }
        }

        if ($isGateQrRequest || $isResendQrRequest) {
            // Selalu tanyakan alasan terlebih dahulu untuk mematuhi SOP jika belum ada alasan
            \Illuminate\Support\Facades\Cache::put($cacheQrKeyPhone, 'awaiting_reason', 600); // 10 menit
            if ($cacheQrKeyNik) {
                \Illuminate\Support\Facades\Cache::put($cacheQrKeyNik, 'awaiting_reason', 600);
            }
            return "Siap {$sapaan}! Sesuai SOP perusahaan, untuk pencatatan sistem HRD mohon sebutkan alasan penggunaan QR Gate hari ini ya (contoh: kartu tertinggal di rumah, kartu hilang, kartu rusak, dll).";
        }

        return null;
    }

    private function processGateQrGeneration(?Karyawan $karyawan, string $sender, string $reason, bool $createNewLog = true): string
    {
        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";

        if (! $karyawan) {
            return "Maaf ya {$sapaan}, nomor WhatsApp ini belum terdaftar di sistem HRIS. Silakan hubungi tim HRD untuk update nomor telepon ya.";
        }

        $user = User::where('username', $karyawan->nik)->first();
        $userId = $user?->id;
        $employeeNik = (string) $karyawan->nik;
        $employeeName = $karyawan->nama_karyawan ?: ($user?->name ?: 'Karyawan');

        // 1. Catat ke GateQrUsageLog jika baru
        if ($createNewLog) {
            $log = GateQrUsageLog::create([
                'user_id' => $userId,
                'nik' => $employeeNik,
                'employee_name' => $employeeName,
                'reason' => trim($reason),
                'used_at' => now(),
            ]);

            // 2. Kirim Notifikasi ke HRD
            try {
                User::query()
                    ->where('level', 2)
                    ->where('is_active', true)
                    ->get()
                    ->each(fn (User $hrd) => $hrd->notify(new GateQrUsageNotification($log)));
            } catch (\Throwable $e) {
                Log::warning('Failed sending GateQrUsageNotification: ' . $e->getMessage());
            }
        }

        // 3. Buat Payload QR Turnstile
        $dateCode = now()->format('ymd'); // YYMMDD (e.g. 260831)
        $qrPayload = json_encode([
            't' => $dateCode . substr($employeeNik, -4),
            'm' => $employeeNik,
            'c' => $dateCode,
            'x' => [[9, 100, 374]],
        ]);

        // 4. URL Gambar QR Code beresolusi tinggi (PNG)
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=500x500&margin=15&data=' . urlencode($qrPayload);

        $tanggalFormatted = now()->locale('id')->translatedFormat('d F Y');
        $caption = "✅ *QR Code Gate Turnstile Berhasil Dibuat!*\n\n" .
            "👤 *Nama*: {$employeeName} (`{$employeeNik}`)\n" .
            "📅 *Berlaku*: Hari Ini ({$tanggalFormatted})\n" .
            "📝 *Alasan*: _{$reason}_\n\n" .
            "📌 *Cara Penggunaan:*\n" .
            "Arahkan gambar QR Code di atas ke scanner pada turnstile gate kantor untuk membuka akses masuk.\n\n" .
            "_Log penggunaan ini sudah otomatis tercatat dan diteruskan ke sistem HRD._";

        // Kirim Gambar QR Langsung ke WhatsApp Karyawan
        $this->sendReply($sender, $caption, $qrImageUrl);

        return '__IMAGE_SENT__';
    }

    private function buildSystemPrompt(?Karyawan $karyawan, bool $isFirstChat = false, int $userLevel = 3): string
    {
        $namaPanggilan = $karyawan ? ucfirst(strtolower(explode(' ', trim($karyawan->nama_karyawan))[0])) : '';
        $sapaan = $namaPanggilan ? "Kak {$namaPanggilan}" : "Kak";

        $roleContext = match ($userLevel) {
            0 => "IT Administrator / Superadmin (Level 0 - Hak Akses Penuh Sistem & Seluruh Data Database)",
            1 => "Direksi / Top Management (Level 1 - Hak Akses Eksekutif Perusahaan)",
            2 => "HRD / HRGA Management (Level 2 - Hak Akses Data Seluruh Karyawan Kantor)",
            default => "Karyawan / Staff (Level 3 - Akses Data Pribadi Diri Sendiri)",
        };

        $prompt = "IDENTITAS DIRI & PERSONA:\n";
        $prompt .= "- Nama kamu: Haris.\n";
        $prompt .= "- Posisi / Identitas kamu: Staff IT di tim Kak Fajar Arifin yang bertugas khusus membantu dan mengelola sistem HRIS HomPim Play via WhatsApp.\n";
        $prompt .= "- Jika ada yang bertanya 'kamu siapa' / 'siapa kamu' / 'ini siapa', jawab ramah dan santai: Kamu adalah Haris, Staff IT dari tim Kak Fajar Arifin khusus sistem HRIS di HomPim Play yang siap membantu informasi presensi, jadwal, cuti, dan kepegawaian kantor.\n";
        $prompt .= "- Rekan chat kamu: {$sapaan} (Wewenang: {$roleContext}).\n\n";

        $prompt .= "ATURAN GAYA BICARA PERCAKAPAN WHATSAPP (PRAKTIS, CERDAS, TO-THE-POINT & ANTI-HALUSINASI):\n";
        $prompt .= "1. DILARANG MEMBUKA DENGAN TEMPLATE KAKU BERULANG seperti 'Halo Kak Fajar! Aku Haris... Ada yang bisa kubantu?'.\n";
        $prompt .= "2. JANGAN TERLALU RAMAH LEBAY / BASA-BASI BERLEBIHAN: Bersikaplah profesional, santai, praktis, dan langsung fokus ke substansi jawaban yang ditanyakan.\n";
        $prompt .= "3. DILARANG KERAS MENGHALUSINASI / BERBOHONG: DILARANG mengatakan 'Haris sudah bantu refresh data / perbaiki sinkronisasi / koneksi' atau alasan palsu sejenisnya. Sampaikan data apa adanya secara transparan.\n";
        $prompt .= "4. JIKA USER MENANYAKAN DATA (Absensi, Cuti, Lembur, Jadwal): Jawab rincian data (tanggal, jam, nama orang, status) secara lengkap dan spesifik. Jangan menyuruh user mengecek sendiri ke portal jika kamu sudah punya datanya!\n";
        if ($userLevel <= 2) {
            $prompt .= "5. HAK AKSES TINGGI (Admin / HRD):\n";
            $prompt .= "   - Jika menanyakan data bawahan, rekan kerja, rekap divisi, statistik kehadiran, atau pengajuan lembur tim, berikan data lengkap dan ringkas.\n";
            $prompt .= "   - Jika menanyakan data pribadi ('jadwal saya', 'cuti saya'), jawab sesuai data pribadi {$sapaan}.\n";
        } else {
            $prompt .= "5. HAK AKSES STAFF BIASA (Level 3):\n";
            $prompt .= "   - Fokus hanya pada data pribadi karyawan sendiri.\n";
        }
        $prompt .= "6. Panggil rekan chat dengan '{$sapaan}'. Gunakan gaya bahasa yang sopan, santai, dan jelas (boleh 1 emoji seperti 👍 / 😊).\n";
        $prompt .= "7. JANGAN PERNAH mengatakan 'sebentar ya aku cek dulu / nanti aku kabari lagi' karena chat dijawab seketika secara real-time.\n";
        $prompt .= "8. Password default portal HRIS adalah 12345678 (delapan digit: 12345678, BUKAN 123456).\n";
        $prompt .= "9. BATASAN TOPIK: HANYA layani pertanyaan seputar HRIS, absensi, jadwal kerja, cuti, izin, lembur/SPL, info kontrak, slip gaji, dan SOP kantor.\n";

        $prompt .= "\nKNOWLEDGE BASE & LOGIKA BISNIS HRIS HOMPIM PLAY (SOP RESMI):\n";
        $prompt .= "- ATURAN PENGAJUAN LEMBUR (SPL / SURAT PERINTAH LEMBUR):\n";
        $prompt .= "  * Lembur HANYA bisa diajukan oleh ATASAN LANGSUNG (Supervisor/Manager) untuk menugaskan bawahan langsungnya lewat menu 'Atasan -> Pengajuan Lembur' di portal HRIS.\n";
        $prompt .= "  * Staf biasa tidak memiliki menu pengajuan lembur mandiri.\n";
        $prompt .= "  * Setiap pengajuan lembur mencatat tanggal pelaksanaan lembur, jam mulai, jam selesai, rincian pekerjaan/alasan, dan status persetujuan (pending/approved/rejected).\n";
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
        $prompt .= "  * LEMBUR (SPL): Diajukan oleh Atasan Langsung untuk bawahan dan disetujui HRD.\n";
        $prompt .= "- QR CODE GATE TURNSTILE / AKSES MASUK KANTOR:\n";
        $prompt .= "  * Bot WhatsApp ini DAPAT LANGSUNG MEMBUATKAN dan MENGIRIMKAN GAMBAR QR Code Gate Turnstile langsung ke chat WhatsApp karyawan.\n";
        $prompt .= "  * JANGAN PERNAH menyuruh karyawan login ke portal web jika mereka meminta QR Code di WhatsApp. Arahkan mereka untuk menyebutkan alasan (misal: 'kartu tertinggal di rumah') agar bot langsung mengirimkan gambar QR Gate ke chat ini.\n";

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

            // A. Cek langsung ke tabel m_karyawan
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

            // B. Cek ke tabel users (jika nomor terdaftar di akun profil portal user)
            $user = User::query()
                ->where(function ($q) use ($phone08, $phone62) {
                    $q->where('phone', $phone08)
                        ->orWhere('phone', $phone62)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', '') IN (?, ?)", [$phone08, $phone62]);
                })
                ->first();

            if ($user && $user->username) {
                $karyawanByUser = Karyawan::where('nik', $user->username)->first();
                if ($karyawanByUser) {
                    return $karyawanByUser;
                }
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

    private function sendReply(string $sender, string $message, ?string $imageUrl = null): bool
    {
        $botUrl = rtrim(trim((string) config('services.hris_agent.bot_url')), '/');
        if ($botUrl !== '') {
            try {
                $payload = [
                    'phone' => $sender,
                    'message' => $message,
                ];
                if ($imageUrl) {
                    $payload['image_url'] = $imageUrl;
                }
                $response = \Illuminate\Support\Facades\Http::timeout(15)->post($botUrl . '/send/message', $payload);
                if ($response->successful()) {
                    return true;
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal kirim via dedicated bot URL, fallback ke WhatsAppService', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($imageUrl) {
            return $this->whatsApp->sendImage($sender, $imageUrl, $message);
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

    /**
     * Menentukan tingkat wewenang (Role-Based Access Control) pengguna WhatsApp.
     * Level 0: IT Administrator / Superadmin (Full global access)
     * Level 1: Direksi / Top Management
     * Level 2: HRD / HRGA Management (Akses seluruh data kepegawaian kantor)
     * Level 3: Karyawan / Staff (Hanya akses data pribadi sendiri)
     */
    public function resolveUserLevel(?Karyawan $karyawan, string $sender): int
    {
        $levels = [];
        $cleanPhone = preg_replace('/[^0-9]/', '', $sender);

        // 1. Cek langsung kolom phone di tabel users
        if ($cleanPhone !== '') {
            $phone08 = str_starts_with($cleanPhone, '62') ? '0' . substr($cleanPhone, 2) : $cleanPhone;
            $phone62 = str_starts_with($cleanPhone, '0') ? '62' . substr($cleanPhone, 1) : $cleanPhone;

            $userLevels = User::query()
                ->where(function ($q) use ($cleanPhone, $phone08, $phone62) {
                    $q->where('phone', $cleanPhone)
                        ->orWhere('phone', $phone08)
                        ->orWhere('phone', $phone62)
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '+', '') IN (?, ?)", [$phone08, $phone62]);
                })
                ->pluck('level')
                ->filter(fn ($l) => $l !== null)
                ->map(fn ($l) => (int) $l)
                ->all();

            if (! empty($userLevels)) {
                $levels = array_merge($levels, $userLevels);
            }
        }

        // 2. Cek dari konfigurasi admin phones di env / config
        $adminPhones = array_filter(array_map('trim', explode(',', (string) config('services.hris_agent.admin_phones', ''))));
        if ($cleanPhone !== '' && in_array($cleanPhone, $adminPhones, true)) {
            $levels[] = 0; // Level 0 Superadmin
        }

        // 3. Cek akun User berdasarkan NIK Karyawan
        if ($karyawan) {
            $user = User::where('username', $karyawan->nik)->first();
            if ($user && $user->level !== null) {
                $levels[] = (int) $user->level;
            }

            // Cek jika jabatan / divisi adalah IT atau HRD/HRGA
            $divisi = strtolower((string) ($karyawan->departement ?: $karyawan->jabatan));
            $jabatan = strtolower((string) $karyawan->jabatan);
            if (str_contains($divisi, 'it') || str_contains($jabatan, 'it') || str_contains($jabatan, 'programmer')) {
                $levels[] = 0; // IT Admin privilege
            } elseif (str_contains($divisi, 'hr') || str_contains($divisi, 'hrd') || str_contains($divisi, 'hrga') || str_contains($divisi, 'personalia')) {
                $levels[] = 2; // HR privilege
            }
        }

        return ! empty($levels) ? min($levels) : 3;
    }
}
