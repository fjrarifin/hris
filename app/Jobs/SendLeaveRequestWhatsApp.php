<?php

namespace App\Jobs;

use App\Models\LeaveRequest;
use App\Models\MKaryawan;
use App\Http\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLeaveRequestWhatsApp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leaveId;

    public function __construct(int $leaveId)
    {
        $this->leaveId = $leaveId;
    }

    public function handle(WhatsAppService $whatsapp)
    {
        // 🔥 LOAD ULANG + RELASI
        $leave = LeaveRequest::with('user')->find($this->leaveId);

        if (!$leave || !$leave->user) {
            logger()->error('WA JOB FAILED - leave or user not found', [
                'leave_id' => $this->leaveId,
            ]);
            return;
        }

        // username = nik
        $nik = trim((string) $leave->user->username);

        $karyawan = MKaryawan::where('nik', $nik)->first();

        if (!$karyawan || !$karyawan->no_hp) {
            logger()->warning('WA SKIPPED - karyawan not found / no hp', [
                'nik' => $nik,
            ]);
            return;
        }

        // TEMPLATE
        $templates = LeaveRequest::whatsappTemplates();
        $template  = $templates[$leave->leave_type] ?? $templates['lainnya'];

        $message = $template($leave, $karyawan);

        $whatsapp->sendMessage(
            $this->normalizePhone($karyawan->no_hp),
            $message
        );
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '62')) return $phone;
        if (str_starts_with($phone, '0')) return '62' . substr($phone, 1);

        return '62' . $phone;
    }
}