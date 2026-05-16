<?php

namespace App\Http\Services;

use App\Models\Karyawan;
use App\Models\User;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Notifications\DirectManagerDecisionNotification;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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

    private function buildMessage($request, $karyawan): string
    {
        $link   = route('approval.show', $request->approval_token);
        $header = "━━━━━━━━━━━━━━━━━━━━━━━\n🏢 *HomPim Play*\n━━━━━━━━━━━━━━━━━━━━━━━";
        $footer = "_Pesan ini dikirim otomatis oleh sistem HomPim Play._\n_Harap tidak membalas pesan ini._\n━━━━━━━━━━━━━━━━━━━━━━━";

        if ($request instanceof LeaveRequest) {
            $start    = Carbon::parse($request->start_date);
            $end      = Carbon::parse($request->end_date);
            $duration = $start->diffInDays($end) + 1;

            return <<<MSG
            {$header}

            Yth. Bapak/Ibu,

            Terdapat *pengajuan cuti* baru yang memerlukan persetujuan Anda.

            👤 *Karyawan*
            {$karyawan->nama_karyawan}

            📅 *Periode Cuti*
            {$start->format('d M Y')} → {$end->format('d M Y')}
            ⏳ Durasi: {$duration} hari kerja

            Mohon segera ditindaklanjuti melalui tautan berikut:
            👇
            {$link}

            {$footer}
            MSG;
        }

        if ($request instanceof PublicHolidayRequest) {
            $holiday = Carbon::parse($request->holiday->holiday_date)->format('d M Y');
            $claim   = Carbon::parse($request->claim_date)->format('d M Y');

            return <<<MSG
            {$header}

            Yth. Bapak/Ibu,

            Terdapat *pengajuan Public Holiday* baru yang memerlukan persetujuan Anda.

            👤 *Karyawan*
            {$karyawan->nama_karyawan}

            🗓️ *Tanggal Hari Libur*
            {$holiday}

            📅 *Tanggal Pengganti*
            {$claim}

            Mohon segera ditindaklanjuti melalui tautan berikut:
            👇
            {$link}

            {$footer}
            MSG;
        }

        return "━━━━━━━━━━━━━━━━━━━━━━━\n🏢 *HomPim Play*\n━━━━━━━━━━━━━━━━━━━━━━━\n\nYth. Bapak/Ibu,\n\nTerdapat pengajuan baru yang memerlukan persetujuan Anda.\n\n👇\n{$link}\n\n{$footer}";
    }

    public function notifySecondManager($request)
    {
        $user = $request->user;

        $karyawan = Karyawan::where('nik', $user->username)->first();

        if (!$karyawan || !$karyawan->atasan_tidak_langsung) {
            return;
        }

        $atasan = Karyawan::where('nama_karyawan', $karyawan->atasan_tidak_langsung)->first();

        if (!$atasan) {
            return;
        }

        if ($atasan->no_hp) {

            $message = "📢 APPROVAL LEVEL 2

            Pengajuan {$karyawan->nama_karyawan} sudah disetujui atasan langsung.

            Silakan lakukan approval tahap kedua.

            🔗 " . route('approval.show', $request->approval_token);

            $this->whatsAppService->sendMessage(
                $atasan->no_hp,
                $message
            );
        }
    }

    public function notifyIndirectManagerOfDirectManagerDecision($request, string $type, string $status): void
    {
        try {
            $user = $request->user;
            $karyawan = Karyawan::where('nik', $user->username)->first();

            if (!$karyawan || !$karyawan->atasan_tidak_langsung) {
                return;
            }

            $atasan = Karyawan::where('nama_karyawan', $karyawan->atasan_tidak_langsung)->first();

            if (!$atasan) {
                return;
            }

            $atasanUser = User::where('username', $atasan->nik)->first();

            if ($atasanUser) {
                $atasanUser->notify(
                    new DirectManagerDecisionNotification($request, $type, $status)
                );
            }

            if ($atasan->no_hp) {
                $message = $this->buildDirectManagerDecisionMessage($request, $karyawan, $type, $status);

                $this->whatsAppService->sendMessage(
                    $this->normalizePhone($atasan->no_hp),
                    $message
                );
            }
        } catch (\Throwable $e) {
            Log::error('Indirect manager decision notification failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildDirectManagerDecisionMessage($request, Karyawan $karyawan, string $type, string $status): string
    {
        $statusLabel = $status === 'approved' ? 'disetujui' : 'ditolak';

        if ($request instanceof LeaveRequest) {
            $start = Carbon::parse($request->start_date)->format('d M Y');
            $end = Carbon::parse($request->end_date)->format('d M Y');

            return "📌 *Keputusan Cuti dari Atasan Langsung*\n\n"
                . "Nama: {$karyawan->nama_karyawan}\n"
                . "Periode: {$start} - {$end}\n\n"
                . "Status: *{$statusLabel}* oleh atasan langsung.";
        }

        if ($request instanceof PublicHolidayRequest) {
            $holiday = optional($request->holiday)->name ?: 'Hari Libur';
            $claim = Carbon::parse($request->claim_date)->format('d M Y');

            return "📌 *Keputusan PH dari Atasan Langsung*\n\n"
                . "Nama: {$karyawan->nama_karyawan}\n"
                . "PH: {$holiday}\n"
                . "Tanggal Pengambilan: {$claim}\n\n"
                . "Status: *{$statusLabel}* oleh atasan langsung.";
        }

        return "Pengajuan {$type} {$karyawan->nama_karyawan} telah {$statusLabel} oleh atasan langsung.";
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
