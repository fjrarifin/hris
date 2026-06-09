<?php

namespace App\Notifications;

use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private object $request, private string $type, private string $status) {}

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $typeLabel = match (strtoupper($this->type)) {
            'IZIN' => 'Izin/Sakit',
            'LEMBUR' => 'Lembur',
            'PH' => 'PH',
            'EO' => 'Extra Off',
            default => 'Cuti',
        };

        $statusLabel = match ($this->status) {
            'approved' => 'disetujui',
            'cancelled' => 'dibatalkan',
            default => 'ditolak',
        };

        return [
            'title' => "{$typeLabel} ".ucfirst($statusLabel),
            'message' => "Pengajuan {$typeLabel} Anda telah {$statusLabel}.",
            'request_id' => $this->request->id,
            'type' => $this->type,
            'status' => $this->status,
            'path' => $this->path(),
            'mobile_path' => $this->mobilePath(),
        ];
    }

    private function path(): string
    {
        return match (strtoupper($this->type)) {
            'LEMBUR' => '/staff/overtime',
            'PH' => '/staff/public-holiday',
            'EO' => '/staff/extra-off',
            'IZIN' => '/staff/permission',
            default => '/staff/leave',
        };
    }

    private function mobilePath(): string
    {
        return match (strtoupper($this->type)) {
            'LEMBUR' => '/requests/overtime',
            'PH' => '/requests/public-holiday',
            'EO' => '/requests/extra-off',
            'IZIN' => '/requests/permission',
            default => '/requests/leave',
        };
    }
}
