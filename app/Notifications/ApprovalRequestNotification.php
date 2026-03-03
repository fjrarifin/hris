<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestNotification extends Notification
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
        return [
            'title' => $this->buildTitle(),
            'message' => $this->buildMessage(),
            'request_id' => $this->request->id,
            'type' => $this->type,
        ];
    }

    private function buildTitle()
    {
        return match ($this->type) {
            'Cuti' => 'Pengajuan Cuti Baru',
            'PH'   => 'Pengajuan PH Baru',
            default => 'Pengajuan Baru'
        };
    }

    private function buildMessage()
    {
        $nama = $this->request->user->name;

        return "Pengajuan {$this->type} dari {$nama} menunggu persetujuan Anda.";
    }
}
