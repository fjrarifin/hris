<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
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
        $requestType = $this->type === 'PH' ? 'PH' : 'cuti';
        $statusLabel = $this->status === 'approved' ? 'disetujui' : 'ditolak';

        return [
            'title' => 'Keputusan Atasan Langsung',
            'message' => "Pengajuan {$requestType} {$employeeName} telah {$statusLabel} oleh atasan langsung.",
            'request_id' => $this->request->id,
            'type' => $this->type,
            'status' => $this->status,
            'model' => $this->request instanceof LeaveRequest
                ? LeaveRequest::class
                : PublicHolidayRequest::class,
        ];
    }
}
