<?php

namespace App\Notifications;

use App\Notifications\Channels\MobilePushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ShortNoticeApprovalNotification extends Notification
{
    use Queueable;

    public function __construct(private object $request, private string $type) {}

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
            ?? 'Karyawan';

        return [
            'title' => "Pengajuan {$typeLabel} Kurang dari 12 Jam",
            'message' => "Pengajuan {$typeLabel} dari {$employeeName} dibuat kurang dari 12 jam sebelum tanggal pengajuan. Mohon HRD koordinasikan approval jika diperlukan.",
            'request_id' => $this->request->id,
            'type' => $this->type,
            'path' => $this->path(),
            'mobile_path' => '/notifications',
        ];
    }

    private function path(): string
    {
        return match (strtoupper($this->type)) {
            'CUTI' => '/hr/approvals/leave',
            'PH' => '/hr/approvals/ph',
            'EO' => '/hr/approvals/extra-off',
            'IZIN', 'SAKIT' => '/hr/approvals/permission',
            default => '/hr/dashboard',
        };
    }
}
