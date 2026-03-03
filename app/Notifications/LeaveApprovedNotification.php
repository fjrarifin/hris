<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveApprovedNotification extends Notification
{
    use Queueable;

    protected $leave;

    public function __construct($leave)
    {
        $this->leave = $leave;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => 'Cuti Disetujui',
            'message' => 'Pengajuan cuti Anda telah disetujui.',
            'leave_id' => $this->leave->id,
            'url' => route('staff.leave.index'),
        ];
    }
}
