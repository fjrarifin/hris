<?php

namespace App\Http\Services;

use App\Models\Karyawan;
use App\Models\User;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use Illuminate\Support\Facades\Log;

class ApprovalNotificationService
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function notifyManager($request, $type)
    {
        try {

            $user = $request->user;

            $karyawan = Karyawan::where('nik', $user->username)->first();

            if (!$karyawan || !$karyawan->nama_atasan_langsung) {
                return;
            }

            $atasan = Karyawan::where('nama_karyawan', $karyawan->nama_atasan_langsung)->first();

            if (!$atasan) {
                return;
            }

            $atasanUser = User::where('username', $atasan->nik)->first();

            if ($atasanUser) {
                $atasanUser->notify(
                    new \App\Notifications\ApprovalRequestNotification($request, $type)
                );
            }

            if ($atasan->no_hp) {

                $message = $this->buildMessage($request, $karyawan);

                $this->whatsAppService->sendMessage(
                    $this->normalizePhone($atasan->no_hp),
                    $message
                );
            }

        } catch (\Throwable $e) {
            Log::error('Approval notification failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function buildMessage($request, $karyawan)
    {
        $link = route('approval.show', $request->approval_token);

        if ($request instanceof LeaveRequest) {

            return
            "📢 PENGAJUAN CUTI BARU 
            
            Karyawan : {$karyawan->nama_karyawan}
            Periode:
            {$request->start_date} - {$request->end_date}

            Klik untuk approve / reject:
            {$link}";
        }

        if ($request instanceof PublicHolidayRequest) {

            return
            "📢 PENGAJUAN PH BARU  
            
            {$karyawan->nama_karyawan}
            Tanggal PH:
            {$request->holiday->holiday_date}

            Tanggal Ambil PH:
            {$request->claim_date}

            Klik untuk approve / reject:
            {$link}";
        }

        return "Pengajuan baru menunggu persetujuan Anda.";
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }

        return $phone;
    }
}
