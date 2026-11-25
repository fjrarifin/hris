<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use App\Models\EventAbsen;
use App\Models\EventAbsenParticipant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventAbsenWhatsAppService
{
    public function __construct(
        protected WhatsAppService $whatsApp
    ) {}

    public function buildPublicUrl(EventAbsen $event): string
    {
        $baseUrl = rtrim(trim((string) config('services.frontend.base_url', 'https://hr.hompimplay.id')), '/');

        return $baseUrl.'/absen-event/'.$event->slug;
    }

    public function formatReminderLabel(?int $minutes): string
    {
        if (! $minutes || $minutes <= 0) {
            return '-';
        }

        if ($minutes % 60 === 0) {
            $hours = (int) ($minutes / 60);

            return "{$hours} jam sebelum acara";
        }

        return "{$minutes} menit sebelum acara";
    }

    public function buildInvitationMessage(EventAbsen $event, string $employeeName): string
    {
        $dateLabel = $event->tanggal_mulai
            ? $event->tanggal_mulai->locale('id')->translatedFormat('l, d F Y')
            : '-';
        $startTime = $event->tanggal_mulai ? $event->tanggal_mulai->format('H:i') : '-';
        $endTime = $event->tanggal_selesai ? $event->tanggal_selesai->format('H:i') : '-';
        $location = trim((string) ($event->lokasi ?? '')) !== '' ? trim((string) $event->lokasi) : '-';
        $publicUrl = $this->buildPublicUrl($event);

        $lines = [
            "Halo *{$employeeName}* 👋",
            '',
            'Anda terdaftar sebagai peserta pada kegiatan berikut:',
            '',
            "📌 *Acara:* {$event->nama_event}",
            "📅 *Tanggal:* {$dateLabel}",
            "⏰ *Waktu:* {$startTime} - {$endTime} WIB",
            "📍 *Lokasi / Ruangan:* {$location}",
        ];

        if (trim((string) ($event->deskripsi ?? '')) !== '') {
            $lines[] = '📝 *Keterangan:* '.trim((string) $event->deskripsi);
        }

        $lines[] = '';
        $lines[] = 'Silakan melakukan presensi melalui tautan berikut saat acara berlangsung:';
        $lines[] = $publicUrl;
        $lines[] = '';
        $lines[] = '_Pesan otomatis dari Nova Agent - HRIS Hompim Play_';

        return implode("\n", $lines);
    }

    public function buildReminderMessage(EventAbsen $event, string $employeeName): string
    {
        $dateLabel = $event->tanggal_mulai
            ? $event->tanggal_mulai->locale('id')->translatedFormat('l, d F Y')
            : '-';
        $startTime = $event->tanggal_mulai ? $event->tanggal_mulai->format('H:i') : '-';
        $endTime = $event->tanggal_selesai ? $event->tanggal_selesai->format('H:i') : '-';
        $location = trim((string) ($event->lokasi ?? '')) !== '' ? trim((string) $event->lokasi) : '-';
        $publicUrl = $this->buildPublicUrl($event);

        $lines = [
            "⏰ *PENGINGAT ACARA* — Halo *{$employeeName}*",
            '',
            'Kegiatan berikut akan segera dimulai:',
            '',
            "📌 *Acara:* {$event->nama_event}",
            "📅 *Tanggal:* {$dateLabel}",
            "⏰ *Waktu:* {$startTime} - {$endTime} WIB",
            "📍 *Lokasi / Ruangan:* {$location}",
        ];

        if (trim((string) ($event->deskripsi ?? '')) !== '') {
            $lines[] = '📝 *Keterangan:* '.trim((string) $event->deskripsi);
        }

        $lines[] = '';
        $lines[] = 'Jangan lupa melakukan absensi melalui tautan berikut:';
        $lines[] = $publicUrl;
        $lines[] = '';
        $lines[] = '_Pengingat otomatis dari Nova Agent - HRIS Hompim Play_';

        return implode("\n", $lines);
    }

    /**
     * Kirim pesan WA undangan awal ke peserta terdaftar yang belum menerima notifikasi awal.
     *
     * @param  array<int, string>|null  $onlyNiks
     * @return array{sent: int, skipped_no_phone: int, failed: int}
     */
    public function sendInitialInvitations(EventAbsen $event, ?array $onlyNiks = null): array
    {
        $query = $event->invitedParticipants()
            ->with(['karyawan:nik,nama_karyawan,no_hp'])
            ->whereNull('initial_notified_at');

        if ($onlyNiks !== null) {
            $query->whereIn('nik_karyawan', $onlyNiks);
        }

        $participants = $query->get();

        $summary = [
            'sent' => 0,
            'skipped_no_phone' => 0,
            'failed' => 0,
        ];

        foreach ($participants as $participant) {
            $phone = trim((string) ($participant->karyawan?->no_hp ?? ''));
            if ($phone === '') {
                $summary['skipped_no_phone']++;

                continue;
            }

            $employeeName = trim((string) ($participant->karyawan?->nama_karyawan ?? $participant->nik_karyawan));
            $message = $this->buildInvitationMessage($event, $employeeName);

            if ($this->sendViaNovaAgent($phone, $message)) {
                $participant->forceFill(['initial_notified_at' => now()])->save();
                $summary['sent']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    /**
     * Kirim pengingat WA untuk semua event aktif yang sudah memasuki waktu reminder.
     *
     * @return array{events_processed: int, sent: int, skipped_no_phone: int, skipped_attended: int, failed: int}
     */
    public function sendDueReminders(): array
    {
        $now = now();

        $events = EventAbsen::query()
            ->where('status', 'aktif')
            ->where('reminder_enabled', true)
            ->whereNotNull('reminder_minutes_before')
            ->where('reminder_minutes_before', '>', 0)
            ->where('tanggal_selesai', '>=', $now)
            ->whereHas('invitedParticipants', function ($q): void {
                $q->whereNull('reminder_sent_at');
            })
            ->with([
                'invitedParticipants' => fn ($q) => $q->whereNull('reminder_sent_at')->with('karyawan:nik,nama_karyawan,no_hp'),
                'absensiEvents:id,id_event_absen,nik_karyawan',
            ])
            ->get();

        $summary = [
            'events_processed' => 0,
            'sent' => 0,
            'skipped_no_phone' => 0,
            'skipped_attended' => 0,
            'failed' => 0,
        ];

        foreach ($events as $event) {
            if (! $event->tanggal_mulai) {
                continue;
            }

            $triggerAt = $event->tanggal_mulai->copy()->subMinutes((int) $event->reminder_minutes_before);
            if ($now->lessThan($triggerAt)) {
                continue;
            }

            $summary['events_processed']++;
            $attendedNiks = $event->absensiEvents
                ->pluck('nik_karyawan')
                ->map(fn ($nik) => strtoupper(trim((string) $nik)))
                ->flip();

            $anyFailed = false;

            /** @var EventAbsenParticipant $participant */
            foreach ($event->invitedParticipants as $participant) {
                $nikUpper = strtoupper(trim((string) $participant->nik_karyawan));
                if ($attendedNiks->has($nikUpper)) {
                    $participant->forceFill(['reminder_sent_at' => $now])->save();
                    $summary['skipped_attended']++;

                    continue;
                }

                $phone = trim((string) ($participant->karyawan?->no_hp ?? ''));
                if ($phone === '') {
                    $participant->forceFill(['reminder_sent_at' => $now])->save();
                    $summary['skipped_no_phone']++;

                    continue;
                }

                $employeeName = trim((string) ($participant->karyawan?->nama_karyawan ?? $participant->nik_karyawan));
                $message = $this->buildReminderMessage($event, $employeeName);

                if ($this->sendViaNovaAgent($phone, $message)) {
                    $participant->forceFill(['reminder_sent_at' => $now])->save();
                    $summary['sent']++;
                } else {
                    $anyFailed = true;
                    $summary['failed']++;
                }
            }

            if (! $anyFailed) {
                $event->forceFill(['reminder_sent_at' => $now])->save();
            }
        }

        return $summary;
    }

    public function sendViaNovaAgent(string $phone, string $message): bool
    {
        $botUrl = rtrim(trim((string) config('services.hris_agent.bot_url')), '/');

        if ($botUrl !== '') {
            try {
                $webhookToken = trim((string) config('services.hris_agent.webhook_token', ''));
                $httpRequest = Http::timeout(15);
                if ($webhookToken !== '') {
                    $httpRequest = $httpRequest->withHeaders(['X-HRIS-Agent-Token' => $webhookToken]);
                }

                $response = $httpRequest->post($botUrl.'/send/message', [
                    'phone' => $phone,
                    'message' => $message,
                ]);

                if ($response->successful()) {
                    return true;
                }

                Log::warning('EventAbsen WA - Nova Agent returned non-2xx, fallback ke WhatsAppService', [
                    'phone' => $phone,
                    'status' => $response->status(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('EventAbsen WA - Gagal kirim via Nova Agent, fallback ke WhatsAppService', [
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            return (bool) $this->whatsApp->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('EventAbsen WA - WhatsAppService gagal', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
