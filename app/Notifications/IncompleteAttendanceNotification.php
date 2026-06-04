<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class IncompleteAttendanceNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly array $record,
        private readonly Carbon $date
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $date = $this->date->copy();
        $dateKey = $date->toDateString();
        $dateLabel = $date->translatedFormat('d F Y');

        return [
            'title' => 'Absensi Belum Lengkap',
            'message' => sprintf(
                'Absensi tanggal %s tercatat %s. Scan masuk: %s, scan pulang: %s.',
                $dateLabel,
                $this->record['finding'] ?? 'belum lengkap',
                $this->scanTime($this->record['scan_in'] ?? null),
                $this->scanTime($this->record['scan_out'] ?? null)
            ),
            'type' => 'attendance_incomplete',
            'date' => $dateKey,
            'path' => '/staff/attendance?start_date='.$dateKey.'&end_date='.$dateKey,
            'mobile_path' => '/tabs/attendance?start_date='.$dateKey.'&end_date='.$dateKey,
        ];
    }

    private function scanTime(?string $time): string
    {
        return $time ? substr($time, 0, 5).' WIB' : '-';
    }
}
