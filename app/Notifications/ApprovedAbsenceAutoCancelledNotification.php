<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovedAbsenceAutoCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $request,
        private readonly string $type,
        private readonly string $employeeName,
        private readonly string $date
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $label = strtoupper($this->type) === 'PH' ? 'PH' : 'Cuti';

        return [
            'title' => "{$label} Otomatis Dibatalkan",
            'message' => "Pengajuan {$label} {$this->employeeName} tanggal {$this->date} dibatalkan karena karyawan tercatat masuk kerja.",
            'request_id' => $this->request->id,
            'type' => strtoupper($this->type),
            'status' => 'cancelled',
        ];
    }
}
