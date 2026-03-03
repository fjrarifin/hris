<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveStatusNotification extends Notification
{
    use Queueable;

    protected $leave;
    protected $status;
    protected $reason;

    public function __construct($leave, $status, $reason = null)
    {
        $this->leave = $leave;
        $this->status = $status;
        $this->reason = $reason;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return [
            'title' => $this->status === 'approved'
                ? 'Cuti Disetujui'
                : 'Cuti Ditolak',

            'message' => $this->status === 'approved'
                ? 'Pengajuan cuti Anda telah disetujui.'
                : ($this->reason ?? 'Pengajuan cuti Anda ditolak.'),

            'leave_id' => $this->leave->id,
            'status' => $this->status,
        ];
    }
}
