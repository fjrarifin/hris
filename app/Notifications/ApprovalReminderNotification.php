<?php

namespace App\Notifications;

use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private object $request,
        private string $type,
        private int $reminderNumber
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $typeLabel = match (strtoupper($this->type)) {
            'CUTI' => 'Cuti',
            'PH' => 'PH',
            'EO' => 'Extra Off',
            'SAKIT' => 'Sakit',
            'IZIN' => 'Izin',
            default => 'Pengajuan',
        };
        $employeeName = $this->request->user?->karyawan?->nama_karyawan
            ?? $this->request->user?->name
            ?? 'Bawahan';

        return [
            'title' => "Reminder Approval {$typeLabel} #{$this->reminderNumber}",
            'message' => "Pengajuan {$typeLabel} dari {$employeeName} belum di-approve dan akan expired jika tidak diproses.",
            'request_id' => $this->request->id,
            'type' => $this->type,
            'reminder_number' => $this->reminderNumber,
            'mobile_path' => '/team-approvals',
        ];
    }
}
