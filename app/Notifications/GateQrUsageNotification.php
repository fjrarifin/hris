<?php

namespace App\Notifications;

use App\Models\GateQrUsageLog;
use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GateQrUsageNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly GateQrUsageLog $log) {}

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'QR Gate Digunakan',
            'message' => "{$this->log->employee_name} ({$this->log->nik}) membuka QR gate. Alasan: {$this->log->reason}",
            'log_id' => $this->log->id,
            'nik' => $this->log->nik,
            'employee_name' => $this->log->employee_name,
            'reason' => $this->log->reason,
            'used_at' => $this->log->used_at?->toIso8601String(),
            'path' => '/hr/dashboard',
            'mobile_path' => '/notifications',
        ];
    }
}
