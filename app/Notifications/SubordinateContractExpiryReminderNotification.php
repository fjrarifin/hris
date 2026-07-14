<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SubordinateContractExpiryReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $contract,
        private readonly object $employee,
        private readonly string $asOfDate
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Kontrak bawahan berakhir dalam 45 hari',
            'message' => "Kontrak {$this->employee->nama_karyawan} ({$this->contract->nik}) berakhir pada {$this->contract->end_date}. Hubungi HRD untuk proses kontrak karyawan tersebut.",
            'type' => 'subordinate_contract_expiry_reminder',
            'contract_id' => $this->contract->id,
            'nik' => $this->contract->nik,
            'employee_name' => $this->employee->nama_karyawan,
            'contract_number' => $this->contract->kontrak_ke,
            'end_date' => $this->contract->end_date,
            'days_before' => 45,
            'as_of_date' => $this->asOfDate,
            'path' => '/staff/contracts',
            'mobile_path' => '/contracts',
        ];
    }
}
