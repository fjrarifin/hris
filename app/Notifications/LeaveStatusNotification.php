<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
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
            'title' => match ($this->status) {
                'approved' => 'Cuti Disetujui',
                'cancelled' => 'Cuti Dibatalkan',
                default => 'Cuti Ditolak',
            },

            'message' => match ($this->status) {
                'approved' => 'Pengajuan cuti Anda telah disetujui.',
                'cancelled' => $this->reason ?? 'Pengajuan cuti Anda dibatalkan oleh HRD.',
                default => $this->reason ?? 'Pengajuan cuti Anda ditolak.',
            },

            'leave_id' => $this->leave->id,
            'status' => $this->status,
        ];
    }
}
