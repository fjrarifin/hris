<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RequestStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private object $request, private string $type, private string $status) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $typeLabel = match (strtoupper($this->type)) {
            'IZIN' => 'Izin/Sakit',
            'LEMBUR' => 'Lembur',
            'PH' => 'PH',
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
        ];
    }
}
