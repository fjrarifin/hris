<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ApprovalRequestNotification extends Notification
{
    use Queueable;

    protected $request;
    protected $type;

    public function __construct($request, $type)
    {
        $this->request = $request;
        $this->type = $type;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->buildTitle(),
            'message' => $this->buildMessage(),
            'request_id' => $this->request->id,
            'type' => $this->type,
        ];
    }

    private function buildTitle()
    {
        return match (strtoupper($this->type)) {
            'CUTI' => 'Pengajuan Cuti Baru',
            'PH' => 'Pengajuan PH Baru',
            'EO' => 'Pengajuan Extra Off Baru',
            'IZIN' => 'Pengajuan Izin/Sakit Baru',
            'SAKIT' => 'Pengajuan Sakit Baru',
            'LEMBUR' => 'Pengajuan Lembur Baru',
            default => 'Pengajuan Baru'
        };
    }

    private function buildMessage()
    {
        $nama = $this->request->user->name;

        $type = match (strtoupper($this->type)) {
            'CUTI' => 'cuti',
            'PH' => 'PH',
            'EO' => 'Extra Off',
            'IZIN' => 'izin/sakit',
            'SAKIT' => 'sakit',
            'LEMBUR' => 'lembur',
            default => strtolower($this->type),
        };

        return "Pengajuan {$type} dari {$nama} menunggu persetujuan Anda.";
    }
}
