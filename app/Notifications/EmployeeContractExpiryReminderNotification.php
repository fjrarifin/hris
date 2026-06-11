<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EmployeeContractExpiryReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly object $contract,
        private readonly object $employee,
        private readonly int $daysBefore,
        private readonly string $asOfDate
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Kontrak {$this->employee->nama_karyawan} habis dalam {$this->daysBefore} hari",
            'message' => "{$this->employee->nama_karyawan} ({$this->contract->nik}) kontrak ke-{$this->contract->kontrak_ke} berakhir pada {$this->contract->end_date}.",
            'type' => 'employee_contract_expiry_reminder',
            'contract_id' => $this->contract->id,
            'nik' => $this->contract->nik,
            'employee_name' => $this->employee->nama_karyawan,
            'contract_number' => $this->contract->kontrak_ke,
            'end_date' => $this->contract->end_date,
            'days_before' => $this->daysBefore,
            'as_of_date' => $this->asOfDate,
            'path' => '/hr/contracts',
        ];
    }
}
