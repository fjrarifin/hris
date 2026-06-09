<?php

namespace App\Notifications;

use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MinimumAttendanceWarningNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $record,
        private readonly string $periodLabel,
        private readonly array $targets
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Minimum Absensi Belum Terpenuhi',
            'message' => "Periode {$this->periodLabel}: kehadiran {$this->record['total_attendance']} hari dan durasi {$this->record['total_work_duration']} masih kurang dari target {$this->targets['ideal_attendance_days']} hari dan {$this->targets['minimum_work_duration']}.",
            'type' => 'attendance_minimum',
            'period' => $this->periodLabel,
            'attendance_diff' => $this->record['attendance_diff_label'],
            'work_duration_diff' => $this->record['work_duration_diff'],
            'status' => 'under',
            'mobile_path' => '/tabs/attendance',
            'path' => '/staff/attendance',
        ];
    }
}
