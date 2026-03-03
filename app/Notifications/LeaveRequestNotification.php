<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class LeaveRequestNotification extends Notification
{
    use Queueable;

    protected $leave;

    public function __construct($leave)
    {
        $this->leave = $leave;
    }

    public function via($notifiable)
    {
        return ['database']; // simpan ke DB
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Pengajuan Cuti Baru',
            'message' => $this->leave->user->name . ' mengajukan cuti.',
            'leave_id' => $this->leave->id,
        ];
    }
}
