<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use App\Models\FingerspotWebhookLog;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Log;

class FingerspotAttendanceWhatsAppNotifier
{
    public function notify(FingerspotWebhookLog $webhookLog): void
    {
        $groupId = config('services.whatsapp.attendance_group_id');

        if (! $groupId || ! config('services.whatsapp.url') || ! config('services.whatsapp.device_id')) {
            Log::warning('Fingerspot attendance WhatsApp notification skipped: WhatsApp config incomplete', [
                'webhook_log_id' => $webhookLog->id,
            ]);

            return;
        }

        if (! $webhookLog->pin) {
            Log::warning('Fingerspot attendance WhatsApp notification skipped: PIN kosong', [
                'webhook_log_id' => $webhookLog->id,
            ]);

            return;
        }

        try {
            $karyawan = Karyawan::query()
                ->where('pin', $webhookLog->pin)
                ->first();

            if ($karyawan) {
                return;
            }

            $message = $this->unregisteredPinMessage($webhookLog);
            app(WhatsAppService::class)->sendMessage($groupId, $message);
        } catch (\Throwable $e) {
            Log::error('Fingerspot attendance WhatsApp notification failed', [
                'webhook_log_id' => $webhookLog->id,
                'pin' => $webhookLog->pin,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function unregisteredPinMessage(FingerspotWebhookLog $webhookLog): string
    {
        $scanTime = $webhookLog->scan
            ? $webhookLog->scan->format('d/m/Y H:i:s')
            : '-';
        $attendanceType = $this->attendanceType($webhookLog->status_scan);
        $headline = $this->headlineType($webhookLog->status_scan);
        $emoji = match ($headline) {
            'MASUK' => '👋',
            'KELUAR' => '🏠',
            default => '📋',
        };

        $lines = [
            $emoji.' *- '.strtolower($attendanceType).'*',
            '',
            '🕐 Waktu     : '.$scanTime,
            '💼 Jabatan   : -',
            '📌 Tipe       : '.$attendanceType,
            '🔢 PIN        : '.($webhookLog->pin ?? '-'),
            '',
            '⚠️ _Hei, sepertinya karyawan ini belum terdaftar di HRIS. Tolong segera didaftarkan ya biar datanya akurat!_',
        ];

        return implode("\n", $lines);
    }

    private function headlineType($value): string
    {
        return match ((string) $value) {
            '0', '2', '4', '6' => 'MASUK',
            '1', '3', '5', '7' => 'KELUAR',
            default => 'ABSENSI',
        };
    }

    private function attendanceType($value): string
    {
        return match ((string) $value) {
            '0' => 'Absen Masuk',
            '1' => 'Absen Keluar',
            '2' => 'Break In',
            '3' => 'Break Out',
            '4' => 'Overtime In',
            '5' => 'Overtime Out',
            '6' => 'Rapat In',
            '7' => 'Rapat Out',
            '8' => 'Custom 1',
            '9' => 'Custom 2',
            default => (string) ($value ?? '-'),
        };
    }
}
