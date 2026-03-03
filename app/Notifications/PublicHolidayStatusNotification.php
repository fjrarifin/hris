<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PublicHolidayStatusNotification extends Notification
{
    use Queueable;

    protected $request;
    protected $type;

    public function __construct($request, $type)
    {
        $this->request = $request;
        $this->type = $type;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $holidayName = $this->request->holiday->name;

        return [
            'title' => $this->getTitle(),
            'message' => $this->getMessage($holidayName),
            'request_id' => $this->request->id,
        ];
    }

    private function getTitle()
    {
        return match ($this->type) {
            'approved' => 'PH Disetujui',
            'rejected' => 'PH Ditolak',
            'cancelled' => 'PH Dibatalkan',
            default => 'Update PH'
        };
    }

    private function getMessage($holiday)
    {
        return match ($this->type) {
            'approved' => "Pengajuan PH {$holiday} disetujui.",
            'rejected' => "Pengajuan PH {$holiday} ditolak.",
            'cancelled' => "Pengajuan PH {$holiday} dibatalkan.",
            default => "Status PH diperbarui."
        };
    }
}
