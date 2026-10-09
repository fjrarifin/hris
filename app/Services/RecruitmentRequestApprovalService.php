<?php

namespace App\Services;

use App\Http\Services\WhatsAppService;
use App\Models\Karyawan;
use App\Models\RecruitmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecruitmentRequestApprovalService
{
    public function __construct(private readonly WhatsAppService $whatsAppService) {}

    public function initiateApproval(RecruitmentRequest $request): void
    {
        $requester = Karyawan::where('nik', $request->requester_nik)->first();

        // 1. Resolve Department Manager
        $manager = Karyawan::where('departement', $request->department)
            ->where(function ($q) {
                $q->where('posisi_title', 'like', '%Manager%')
                    ->where('posisi_title', 'not like', '%Asst%')
                    ->where('posisi_title', 'not like', '%GM%');
            })
            ->first();

        if (! $manager && $requester?->atasan_langsung_nik) {
            $manager = Karyawan::where('nik', $requester->atasan_langsung_nik)->first();
        }

        // 2. Resolve GM
        $gm = Karyawan::where(function ($q) {
            $q->where('posisi_title', 'like', '%GM%')
                ->orWhere('posisi_title', 'like', '%General Manager%');
        })->first();

        // 3. Resolve HRBP Manager
        $hrbp = Karyawan::where('departement', 'HRBP')
            ->where(function ($q) {
                $q->where('posisi_title', 'like', '%Manager%')
                    ->where('posisi_title', 'not like', '%Asst%');
            })
            ->first();

        $token = (string) Str::uuid();
        $expiresAt = now()->addHours((int) config('services.public_approval.expires_hours', 72));

        $updateData = [
            'manager_nik' => $manager?->nik,
            'manager_name' => $manager?->nama_karyawan,
            'gm_nik' => $gm?->nik,
            'gm_name' => $gm?->nama_karyawan,
            'hrbp_nik' => $hrbp?->nik,
            'hrbp_name' => $hrbp?->nama_karyawan,
            'approval_token' => $token,
            'approval_token_expires_at' => $expiresAt,
            'hiring_status' => 'pending',
        ];

        // Check if requester IS the department manager
        $isRequesterManager = $manager && $requester && $requester->nik === $manager->nik;

        if ($isRequesterManager) {
            $updateData['manager_status'] = 'approved';
            $updateData['manager_approved_at'] = now();
            $updateData['manager_notes'] = 'Disetujui otomatis oleh pengaju (Manager Departemen)';

            if ($request->requires_gm && $gm) {
                $updateData['approval_step'] = 'gm';
                $updateData['gm_status'] = 'pending';
            } else {
                $updateData['approval_step'] = 'hrbp';
                $updateData['hrbp_status'] = 'pending';
            }
        } else {
            $updateData['approval_step'] = 'manager';
            $updateData['manager_status'] = 'pending';
            if ($request->requires_gm) {
                $updateData['gm_status'] = 'pending';
            }
        }

        $request->update($updateData);

        // Notify active step approver
        $this->notifyCurrentApprover($request->fresh());
    }

    public function approve(RecruitmentRequest $request, ?string $approverNik = null, ?string $notes = null): bool
    {
        $step = $request->approval_step;

        if ($step === 'manager') {
            $request->manager_status = 'approved';
            $request->manager_approved_at = now();
            $request->manager_notes = $notes ?: $request->manager_notes;
            if ($approverNik) {
                $request->manager_nik = $approverNik;
                $approverKaryawan = Karyawan::where('nik', $approverNik)->first();
                if ($approverKaryawan) {
                    $request->manager_name = $approverKaryawan->nama_karyawan;
                }
            }

            if ($request->requires_gm && $request->gm_nik) {
                $request->approval_step = 'gm';
                $request->approval_token = (string) Str::uuid();
                $request->approval_token_expires_at = now()->addHours((int) config('services.public_approval.expires_hours', 72));
                $request->save();

                $this->notifyCurrentApprover($request);
                return true;
            }

            // Move to HRBP
            $request->approval_step = 'hrbp';
            $request->approval_token = (string) Str::uuid();
            $request->approval_token_expires_at = now()->addHours((int) config('services.public_approval.expires_hours', 72));
            $request->save();

            $this->notifyCurrentApprover($request);
            return true;
        }

        if ($step === 'gm') {
            $request->gm_status = 'approved';
            $request->gm_approved_at = now();
            $request->gm_notes = $notes ?: $request->gm_notes;
            if ($approverNik) {
                $request->gm_nik = $approverNik;
                $approverKaryawan = Karyawan::where('nik', $approverNik)->first();
                if ($approverKaryawan) {
                    $request->gm_name = $approverKaryawan->nama_karyawan;
                }
            }

            // Move to HRBP
            $request->approval_step = 'hrbp';
            $request->approval_token = (string) Str::uuid();
            $request->approval_token_expires_at = now()->addHours((int) config('services.public_approval.expires_hours', 72));
            $request->save();

            $this->notifyCurrentApprover($request);
            return true;
        }

        if ($step === 'hrbp') {
            $request->hrbp_status = 'approved';
            $request->hrbp_approved_at = now();
            $request->hrbp_notes = $notes ?: $request->hrbp_notes;
            if ($approverNik) {
                $request->hrbp_nik = $approverNik;
                $approverKaryawan = Karyawan::where('nik', $approverNik)->first();
                if ($approverKaryawan) {
                    $request->hrbp_name = $approverKaryawan->nama_karyawan;
                }
            }

            // Final approval complete!
            $request->approval_step = 'completed';
            $request->status = 'approved';
            $request->hiring_status = 'accepted';
            $request->approval_token = null;
            $request->approval_token_expires_at = null;
            $request->save();

            $this->notifyRequesterFinalDecision($request, 'approved');
            return true;
        }

        return false;
    }

    public function reject(RecruitmentRequest $request, ?string $approverNik = null, ?string $notes = null): bool
    {
        $step = $request->approval_step;

        if ($step === 'manager') {
            $request->manager_status = 'rejected';
            $request->manager_notes = $notes;
        } elseif ($step === 'gm') {
            $request->gm_status = 'rejected';
            $request->gm_notes = $notes;
        } elseif ($step === 'hrbp') {
            $request->hrbp_status = 'rejected';
            $request->hrbp_notes = $notes;
        }

        $request->approval_step = 'rejected';
        $request->status = 'rejected';
        $request->hiring_status = 'rejected';
        $request->hrd_notes = $notes ?: $request->hrd_notes;
        $request->approval_token = null;
        $request->approval_token_expires_at = null;
        $request->save();

        $this->notifyRequesterFinalDecision($request, 'rejected', $notes);
        return true;
    }

    public function notifyCurrentApprover(RecruitmentRequest $request): void
    {
        $step = $request->approval_step;
        $targetKaryawan = null;
        $roleTitle = '';

        if ($step === 'manager') {
            $targetKaryawan = Karyawan::where('nik', $request->manager_nik)->first();
            $roleTitle = 'Manager Departemen ' . $request->department;
        } elseif ($step === 'gm') {
            $targetKaryawan = Karyawan::where('nik', $request->gm_nik)->first();
            $roleTitle = 'General Manager';
        } elseif ($step === 'hrbp') {
            $targetKaryawan = Karyawan::where('nik', $request->hrbp_nik)->first();
            $roleTitle = 'Manager HRBP';
        }

        if (! $targetKaryawan || ! $targetKaryawan->no_hp) {
            Log::warning("Recruitment Request WA skipped: approver phone missing for step {$step}", [
                'request_id' => $request->id,
                'target_nik' => $targetKaryawan?->nik,
            ]);
            return;
        }

        $link = config('services.frontend.base_url') . '/approval/' . $request->approval_token;

        $message = $this->buildApprovalMessage($request, $targetKaryawan, $roleTitle, $link);
        $this->whatsAppService->sendMessage($targetKaryawan->no_hp, $message);
    }

    private function notifyRequesterFinalDecision(RecruitmentRequest $request, string $decision, ?string $notes = null): void
    {
        $requester = Karyawan::where('nik', $request->requester_nik)->first();
        if (! $requester || ! $requester->no_hp) {
            return;
        }

        $isApproved = $decision === 'approved';
        $statusText = $isApproved ? '✅ *DISETUJUI (ACCEPTED)*' : '❌ *DITOLAK (REJECTED)*';

        $msg = "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🏢 *HomPim Play - Status Manpower Request*\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Yth. {$requester->nama_karyawan},\n\n";
        $msg .= "Pengajuan Rekrutmen (Manpower Request) Anda telah selesai ditinjau dengan hasil: {$statusText}\n\n";
        $msg .= "📋 *Posisi*        : {$request->title}\n";
        $msg .= "🏢 *Departemen*    : {$request->department} / {$request->unit}\n";
        $msg .= "👥 *Jumlah*        : {$request->quantity} Orang\n";
        $msg .= "📄 *Status Kerja*  : " . strtoupper((string)$request->employment_status) . "\n";
        $msg .= "🔄 *Jenis Hiring*  : " . ($request->hiring_type === 'replacement' ? 'Replacement' : 'New Hiring') . "\n";

        if (! $isApproved && $notes) {
            $msg .= "📝 *Catatan*       : {$notes}\n";
        } elseif ($isApproved) {
            $msg .= "\nPermohonan Anda akan segera diproses oleh Tim Rekrutmen HR. Anda dapat memantau progresnya di menu Pengajuan Rekrutmen.\n";
        }

        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "_Pesan otomatis sistem HRIS HomPim Play._";

        $this->whatsAppService->sendMessage($requester->no_hp, $msg);
    }

    private function buildApprovalMessage(RecruitmentRequest $request, Karyawan $approver, string $roleTitle, string $link): string
    {
        $requester = Karyawan::where('nik', $request->requester_nik)->first();
        $requesterName = $requester ? $requester->nama_karyawan : $request->requester_nik;

        $hiringTypeText = $request->hiring_type === 'replacement' ? 'Replacement (Penggantian)' : 'New Hiring (Penambahan)';
        $contractText = strtoupper((string) ($request->employment_status ?: 'PKWT'));
        $targetDate = $request->start_date ? Carbon::parse($request->start_date)->isoFormat('D MMM YYYY') : '-';

        $subordinatesCount = is_array($request->subordinates) ? count($request->subordinates) : 0;
        $subordinateText = '-';
        if ($subordinatesCount > 0) {
            $names = array_map(function ($sub) {
                return is_array($sub) ? ($sub['nama_karyawan'] ?? $sub['nama'] ?? '') : (string)$sub;
            }, $request->subordinates);
            $subordinateText = implode(', ', array_filter($names));
        }

        $msg = "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "🏢 *HomPim Play - Manpower Request Approval*\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "Yth. Bapak/Ibu *{$approver->nama_karyawan}* ({$roleTitle}),\n\n";
        $msg .= "Terdapat *Pengajuan Rekrutmen (Manpower Request)* baru yang memerlukan persetujuan Anda:\n\n";
        $msg .= "👤 *Diajukan Oleh*    : {$requesterName}\n";
        $msg .= "📋 *Posisi Diajukan*  : *{$request->title}*\n";
        $msg .= "🏢 *Departemen / Unit* : {$request->department} - {$request->unit}\n";
        $msg .= "👥 *Jumlah Dibutuhkan*: {$request->quantity} Orang\n";
        $msg .= "📄 *Status Kontrak*   : {$contractText}\n";
        $msg .= "🔄 *Jenis Hiring*     : {$hiringTypeText}\n";

        if ($request->hiring_type === 'replacement') {
            $msg .= "👤 *Menggantikan*     : {$request->replaced_employee_name} ({$request->replaced_employee_position})\n";
        }

        if ($request->direct_report_name) {
            $msg .= "🎯 *Direct Report*    : {$request->direct_report_name}\n";
        }

        if ($subordinateText !== '-') {
            $msg .= "👥 *Subordinate*      : {$subordinateText}\n";
        }

        $msg .= "📅 *Target Mulai*     : {$targetDate}\n";

        if ($request->description) {
            $msg .= "📝 *Keterangan*       : {$request->description}\n";
        }

        $msg .= "\nMohon segera ditinjau dan berikan keputusan melalui tautan berikut:\n";
        $msg .= "👇\n";
        $msg .= "{$link}\n\n";
        $msg .= "━━━━━━━━━━━━━━━━━━━━━━━\n";
        $msg .= "_Link ini berlaku selama " . config('services.public_approval.expires_hours', 72) . " jam._\n";
        $msg .= "_Pesan otomatis sistem HRIS HomPim Play._";

        return $msg;
    }
}
