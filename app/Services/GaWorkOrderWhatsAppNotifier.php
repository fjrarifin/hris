<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GaWorkOrderWhatsAppNotifier
{
    public function __construct(private readonly WhatsAppService $whatsAppService) {}

    /**
     * Notify every selected manager-level approver via WhatsApp when a new Work Order is submitted.
     *
     * @param  array<int, array{hris_user_id:int, nik:string, name:string, email:?string}>  $approvers
     * @param  array{id:int|string, area_name:string, job_type:string, project_description:string, target_date:?string}  $wo
     */
    public function notifyApproversOnCreated(array $approvers, array $wo, object $requester, array $approvalTokens = []): void
    {
        if (empty($approvers)) {
            return;
        }

        $tokenByNik = [];
        foreach ($approvalTokens as $item) {
            $itemNik = strtoupper(trim((string) ($item['nik'] ?? '')));
            $itemToken = trim((string) ($item['token'] ?? ''));
            if ($itemNik !== '' && $itemToken !== '') {
                $tokenByNik[$itemNik] = $itemToken;
            }
        }
        $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');

        $niks = array_values(array_unique(array_filter(array_map(
            static fn (array $a): string => strtoupper(trim((string) ($a['nik'] ?? ''))),
            $approvers
        ))));

        if (empty($niks)) {
            return;
        }

        $phonesByNik = DB::table('m_karyawan')
            ->whereIn(DB::raw('UPPER(TRIM(nik))'), $niks)
            ->get(['nik', 'nama_karyawan', 'no_hp'])
            ->keyBy(fn ($row) => strtoupper(trim((string) $row->nik)));

        $requesterName = trim((string) ($requester->name ?? 'Karyawan'));
        $requesterNik = strtoupper(trim((string) ($requester->username ?? $requester->nik ?? '-')));
        $woId = $wo['id'] ?? '-';
        $area = $wo['area_name'] ?? '-';
        $jobType = $wo['job_type'] ?? 'WORK ORDER';
        $desc = trim((string) ($wo['project_description'] ?? '-'));
        $target = ! empty($wo['target_date']) ? $wo['target_date'] : '-';

        foreach ($approvers as $approver) {
            $nik = strtoupper(trim((string) ($approver['nik'] ?? '')));
            $row = $phonesByNik->get($nik);
            $phone = trim((string) ($row->no_hp ?? ''));

            if ($phone === '') {
                Log::warning('WO WA notification skipped: approver phone missing', [
                    'wo_id' => $woId,
                    'approver_nik' => $nik,
                ]);

                continue;
            }

            $approverName = trim((string) ($row->nama_karyawan ?? $approver['name'] ?? 'Atasan'));
            $publicToken = $tokenByNik[$nik] ?? '';
            $publicLinkLine = ($frontendUrl !== '' && $publicToken !== '')
                ? "🔗 *Link Approval Cepat (Tanpa Login):*\n{$frontendUrl}/wo/{$publicToken}\n\n"
                : '';

            $message = "🔧 *PENGAJUAN WORK ORDER BARU*\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                ."Halo *{$approverName}*,\n"
                ."Anda ditunjuk sebagai approver untuk pengajuan Work Order berikut:\n\n"
                ."🆔 *No. WO* : #{$woId}\n"
                ."👤 *Pemohon* : {$requesterName} ({$requesterNik})\n"
                ."📍 *Area / Unit* : {$area}\n"
                ."🛠️ *Jenis* : {$jobType}\n"
                ."📝 *Deskripsi* : {$desc}\n"
                ."📅 *Target* : {$target}\n\n"
                .$publicLinkLine
                ."Silakan tinjau lampiran foto/dokumen dan berikan persetujuan melalui tautan di atas atau *HRIS → Menu Atasan → Approval Work Order*.\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━\n"
                .'_Pesan otomatis sistem HRIS & GA HomPim Play._';

            try {
                $this->whatsAppService->sendMessage($phone, $message);
            } catch (\Throwable $e) {
                Log::warning('WO WA notification failed for approver', [
                    'wo_id' => $woId,
                    'approver_nik' => $nik,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notify the Work Order requester via WhatsApp when an approver approves or rejects WO / RAB.
     */
    public function notifyRequesterOnDecision(
        array $detail,
        object $actor,
        string $stage,
        string $decision,
        ?string $comment,
        ?string $newStatus
    ): void {
        $requesterNik = strtoupper(trim((string) ($detail['requester']['nik'] ?? '')));
        if ($requesterNik === '') {
            return;
        }

        $actorNik = strtoupper(trim((string) ($actor->username ?? $actor->nik ?? '')));
        if ($requesterNik === $actorNik) {
            return;
        }

        $karyawan = DB::table('m_karyawan')
            ->whereRaw('UPPER(TRIM(nik)) = ?', [$requesterNik])
            ->first(['nama_karyawan', 'no_hp']);

        $phone = trim((string) ($karyawan->no_hp ?? ''));
        if ($phone === '') {
            return;
        }

        $wo = $detail['work_order'] ?? [];
        $woId = $wo['excel_no'] ?? $wo['id'] ?? '-';
        $area = $wo['area_name'] ?? '-';
        $requesterName = trim((string) ($karyawan->nama_karyawan ?? $detail['requester']['name'] ?? 'Pemohon'));
        $actorName = trim((string) ($actor->name ?? 'Approver'));
        $stageLabel = $stage === 'rab' ? 'RAB Biaya' : 'Pengajuan Work Order';
        $decisionLabel = $decision === 'approve'
            ? 'DISETUJUI ✅'
            : ($stage === 'rab' ? 'DIMINTA REVISI ⚠️' : 'DITOLAK ❌');

        $statusLabels = [
            'WAITING_APPROVAL' => 'Menunggu Persetujuan',
            'PENDING_GA' => 'Menunggu Review GA',
            'RAB_PENDING' => 'Menunggu Persetujuan RAB',
            'RAB_APPROVED' => 'RAB Disetujui',
            'SPL_PENDING' => 'Menunggu Finance (SPL)',
            'BUYING_MATERIAL' => 'Pembelian Material',
            'ON_PROGRESS' => 'Sedang Dikerjakan',
            'REVIEW' => 'Menunggu Review Pemohon',
            'DONE' => 'Selesai',
            'HOLD' => 'Ditunda',
            'REJECTED' => 'Ditolak',
            'BATAL' => 'Dibatalkan',
        ];
        $rawStatus = (string) $newStatus;
        $statusReadable = str_starts_with($rawStatus, 'REV-')
            ? 'Revisi '.substr($rawStatus, 4)
            : ($statusLabels[$rawStatus] ?? $rawStatus);

        $message = "📋 *UPDATE PERSETUJUAN WORK ORDER*\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Halo *{$requesterName}*,\n"
            ."{$stageLabel} Anda telah ditindaklanjuti:\n\n"
            ."🆔 *No. WO* : #{$woId}\n"
            ."📍 *Area / Unit* : {$area}\n"
            ."👤 *Approver* : {$actorName}\n"
            ."📌 *Keputusan* : *{$decisionLabel}*\n"
            ."📊 *Status Terbaru* : *{$statusReadable}*\n";

        if (trim((string) $comment) !== '') {
            $message .= '💬 *Catatan* : '.trim((string) $comment)."\n";
        }

        $message .= "\nPantau progres lengkapnya melalui menu *Work Order* di HRIS.\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━\n"
            .'_Pesan otomatis sistem HRIS & GA HomPim Play._';

        try {
            $this->whatsAppService->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WO WA decision notification failed', [
                'wo_id' => $woId,
                'requester_nik' => $requesterNik,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify the requester via WhatsApp when the GA technician finishes work and sets status to REVIEW.
     */
    public function notifyRequesterForReview(array $payload): void
    {
        $requesterNik = strtoupper(trim((string) ($payload['requester_nik'] ?? '')));
        if ($requesterNik === '') {
            return;
        }

        $karyawan = DB::table('m_karyawan')
            ->whereRaw('UPPER(TRIM(nik)) = ?', [$requesterNik])
            ->first(['nama_karyawan', 'no_hp']);

        $phone = trim((string) ($karyawan->no_hp ?? ''));
        if ($phone === '') {
            Log::warning('WO WA review notification skipped: requester phone missing', [
                'wo_id' => $payload['work_order_id'] ?? null,
                'requester_nik' => $requesterNik,
            ]);

            return;
        }

        $requesterName = trim((string) ($karyawan->nama_karyawan ?? $payload['requester_name'] ?? 'Pemohon'));
        $woNo = $payload['work_order_no'] ?? $payload['work_order_id'] ?? '-';
        $area = trim((string) ($payload['area_name'] ?? '-'));
        $techName = trim((string) ($payload['technician_name'] ?? 'Teknisi GA'));
        $notes = trim((string) ($payload['notes'] ?? ''));

        $message = "✅ *PEKERJAAN WORK ORDER SELESAI DIKERJAKAN*\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━\n\n"
            ."Halo *{$requesterName}*,\n"
            ."Work Order *#{$woNo}* di area *{$area}* telah selesai dikerjakan oleh teknisi dan menunggu pengecekan Anda:\n\n"
            ."🆔 *No. WO* : #{$woNo}\n"
            ."📍 *Area / Unit* : {$area}\n"
            ."🛠️ *Teknisi* : {$techName}\n";

        if ($notes !== '') {
            $message .= "📝 *Catatan Teknisi* : {$notes}\n";
        }

        $message .= "\nHarap tinjau kembali hasil pekerjaan tersebut di lapangan, lalu buka menu *Work Order* di HRIS untuk:\n"
            ."• Konfirmasi *Selesai (Done)* jika hasil sudah sesuai, atau\n"
            ."• Ajukan *Revisi* (sertakan catatan & 1–3 foto) jika masih ada yang perlu diperbaiki.\n"
            ."━━━━━━━━━━━━━━━━━━━━━━━\n"
            .'_Pesan otomatis sistem HRIS & GA HomPim Play._';

        try {
            $this->whatsAppService->sendMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::warning('WO WA review notification failed', [
                'wo_id' => $woNo,
                'requester_nik' => $requesterNik,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Notify requester and all initial approvers via WhatsApp (with public approval links) when GA submits RAB.
     */
    public function notifyApproversOnRabSubmitted(array $payload): void
    {
        $approvers = is_array($payload['approvers'] ?? null) ? $payload['approvers'] : [];
        if (empty($approvers)) {
            return;
        }

        $niks = array_values(array_unique(array_filter(array_map(
            static fn (array $a): string => strtoupper(trim((string) ($a['nik'] ?? ''))),
            $approvers
        ))));
        if (empty($niks)) {
            return;
        }

        $phonesByNik = DB::table('m_karyawan')
            ->whereIn(DB::raw('UPPER(TRIM(nik))'), $niks)
            ->get(['nik', 'nama_karyawan', 'no_hp'])
            ->keyBy(fn ($row) => strtoupper(trim((string) $row->nik)));

        $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
        $woNo = $payload['work_order_no'] ?? $payload['work_order_id'] ?? '-';
        $area = trim((string) ($payload['area_name'] ?? '-'));
        $submittedBy = trim((string) ($payload['submitted_by'] ?? 'Admin GA'));
        $itemCount = (int) ($payload['item_count'] ?? 0);
        $rabTotalFormatted = 'Rp '.number_format((float) ($payload['rab_total'] ?? 0), 0, ',', '.');
        $rabNotes = trim((string) ($payload['rab_notes'] ?? ''));

        foreach ($approvers as $ap) {
            $nik = strtoupper(trim((string) ($ap['nik'] ?? '')));
            $row = $phonesByNik->get($nik);
            $phone = trim((string) ($row->no_hp ?? ''));
            if ($phone === '') {
                Log::warning('WO RAB WA notification skipped: phone missing', [
                    'wo_no' => $woNo,
                    'nik' => $nik,
                ]);

                continue;
            }

            $name = trim((string) ($row->nama_karyawan ?? $ap['name'] ?? 'Bapak/Ibu'));
            $token = trim((string) ($ap['token'] ?? ''));
            $publicLinkLine = ($frontendUrl !== '' && $token !== '')
                ? "🔗 *Link Approval RAB (Tanpa Login):*\n{$frontendUrl}/wo/{$token}\n\n"
                : '';

            $message = "💰 *PENGAJUAN PERSETUJUAN RAB WORK ORDER*\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━\n\n"
                ."Halo *{$name}*,\n"
                ."Tim GA telah menyusun rincian biaya (RAB) untuk Work Order berikut dan membutuhkan persetujuan Anda:\n\n"
                ."🆔 *No. WO* : #{$woNo}\n"
                ."📍 *Area / Unit* : {$area}\n"
                ."🧾 *Jumlah Item* : {$itemCount} item\n"
                ."💵 *Total RAB* : *{$rabTotalFormatted}*\n"
                ."👤 *Disusun Oleh* : {$submittedBy}\n";

            if ($rabNotes !== '') {
                $message .= "📝 *Catatan GA* : {$rabNotes}\n";
            }

            $message .= "\n{$publicLinkLine}"
                ."Silakan tinjau rincian item & biaya melalui tautan di atas atau melalui *HRIS* (Setujui RAB atau Minta Revisi).\n"
                ."━━━━━━━━━━━━━━━━━━━━━━━\n"
                .'_Pesan otomatis sistem HRIS & GA HomPim Play._';

            try {
                $this->whatsAppService->sendMessage($phone, $message);
            } catch (\Throwable $e) {
                Log::warning('WO RAB WA notification failed', [
                    'wo_no' => $woNo,
                    'nik' => $nik,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
