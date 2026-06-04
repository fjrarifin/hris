<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\OvertimeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DirectManagerDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(private object $request, private string $type, private string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $employeeName = $this->request->user->name;
        $requestType = match (strtoupper($this->type)) {
            'PH' => 'PH',
            'EO' => 'Extra Off',
            'IZIN' => 'izin/sakit',
            'LEMBUR' => 'lembur',
            default => 'cuti',
        };
        $statusLabel = $this->status === 'approved' ? 'disetujui' : 'ditolak';

        return [
            'title' => 'Keputusan Atasan Langsung',
            'message' => "Pengajuan {$requestType} {$employeeName} telah {$statusLabel} oleh atasan langsung.",
            'request_id' => $this->request->id,
            'type' => $this->type,
            'status' => $this->status,
            'model' => match (true) {
                $this->request instanceof LeaveRequest => LeaveRequest::class,
                $this->request instanceof PublicHolidayRequest => PublicHolidayRequest::class,
                $this->request instanceof ExtraOffRequest => ExtraOffRequest::class,
                $this->request instanceof EmployeePermission => EmployeePermission::class,
                $this->request instanceof OvertimeRequest => OvertimeRequest::class,
                default => get_class($this->request),
            },
        ];
    }
}
