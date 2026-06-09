<?php

namespace App\Notifications;

use App\Models\Karyawan;
use App\Models\User;
use App\Notifications\Channels\MobilePushChannel;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class HrCancellationRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $request,
        private readonly string $type,
        private readonly Karyawan $employee,
        private readonly User $supervisor,
        private readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', MobilePushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        $label = strtoupper($this->type) === 'PH' ? 'PH' : 'Cuti';

        return [
            'title' => "Permintaan Pembatalan {$label}",
            'message' => "{$this->supervisor->name} meminta HRD membatalkan {$label} {$this->employee->nama_karyawan} karena karyawan tetap masuk kerja.",
            'request_id' => $this->request->id,
            'employee_nik' => $this->employee->nik,
            'employee_name' => $this->employee->nama_karyawan,
            'type' => strtoupper($this->type),
            'status' => 'cancellation_requested',
            'reason' => $this->reason,
            'requested_by' => $this->supervisor->name,
            'requested_at' => Carbon::now()->toIso8601String(),
            'path' => '/hr/approvals/leave',
            'mobile_path' => '/notifications',
        ];
    }
}
