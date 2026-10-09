<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\RecruitmentRequest;
use App\Models\RecruitmentVacancy;
use App\Services\HrdAuditLogService;
use App\Services\RecruitmentRequestApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrRecruitmentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RecruitmentRequest::query()
                ->with(['requester', 'vacancy', 'replacedEmployee', 'directReport', 'manager', 'gm', 'hrbp', 'picHiring'])
                ->latest()
                ->get()
        );
    }

    public function options(Request $request): JsonResponse
    {
        $recruiters = Karyawan::query()
            ->where(function ($q) {
                $q->where('departement', 'HRBP')
                    ->orWhere('divisi', 'Business Partner')
                    ->orWhere('posisi_title', 'like', '%HR%');
            })
            ->select('nik', 'nama_karyawan', 'posisi_title', 'departement')
            ->orderBy('nama_karyawan')
            ->get();

        if ($recruiters->isEmpty()) {
            $recruiters = Karyawan::query()
                ->select('nik', 'nama_karyawan', 'posisi_title', 'departement')
                ->orderBy('nama_karyawan')
                ->get();
        }

        return response()->json([
            'recruiters' => $recruiters,
            'all_employees' => Karyawan::query()->select('nik', 'nama_karyawan', 'posisi_title', 'departement')->orderBy('nama_karyawan')->get(),
        ]);
    }

    public function decide(Request $request, RecruitmentRequest $recruitmentRequest): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'hrd_notes' => ['nullable', 'string'],
            'vacancy_link_mode' => ['nullable', 'in:none,existing,new'],
            'vacancy_id' => ['nullable', 'exists:recruitment_vacancies,id'],
            'pic_hiring_nik' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'finished_date' => ['nullable', 'date'],
            'hiring_status' => ['nullable', 'in:pending,accepted,rejected,ongoing,hired,canceled'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($recruitmentRequest);

        $updateData = [
            'status' => $payload['status'],
            'hrd_notes' => $payload['hrd_notes'] ?? null,
            'hiring_status' => $payload['hiring_status'] ?? ($payload['status'] === 'approved' ? 'accepted' : 'rejected'),
        ];

        if (! empty($payload['pic_hiring_nik'])) {
            $pic = Karyawan::where('nik', $payload['pic_hiring_nik'])->first();
            $updateData['pic_hiring_nik'] = $payload['pic_hiring_nik'];
            $updateData['pic_hiring_name'] = $pic?->nama_karyawan;
        }

        if (isset($payload['start_date'])) {
            $updateData['start_date'] = $payload['start_date'];
        }
        if (isset($payload['finished_date'])) {
            $updateData['finished_date'] = $payload['finished_date'];
        }

        if ($payload['status'] === 'approved') {
            $updateData['hrbp_status'] = 'approved';
            $updateData['hrbp_approved_at'] = now();
            $updateData['approval_step'] = 'completed';

            $mode = $payload['vacancy_link_mode'] ?? 'none';
            if ($mode === 'new') {
                $vacancy = RecruitmentVacancy::query()->create([
                    'title' => $recruitmentRequest->title,
                    'department' => $recruitmentRequest->department,
                    'unit' => $recruitmentRequest->unit,
                    'status' => 'open',
                ]);
                $updateData['vacancy_id'] = $vacancy->id;
            } elseif ($mode === 'existing' && ! empty($payload['vacancy_id'])) {
                $updateData['vacancy_id'] = $payload['vacancy_id'];
            }
        } else {
            $updateData['approval_step'] = 'rejected';
        }

        $recruitmentRequest->update($updateData);

        if ($payload['status'] === 'approved') {
            app(RecruitmentRequestApprovalService::class)->approve($recruitmentRequest, $request->user()->username, $payload['hrd_notes'] ?? null);
        } else {
            app(RecruitmentRequestApprovalService::class)->reject($recruitmentRequest, $request->user()->username, $payload['hrd_notes'] ?? null);
        }

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentRequest',
            'decided',
            "Recruitment Request #{$recruitmentRequest->id} decided as {$recruitmentRequest->status}",
            $beforeAudit,
            $recruitmentRequest->fresh(),
            RecruitmentRequest::class,
            $recruitmentRequest->id
        );

        return response()->json([
            'message' => 'Keputusan pengajuan rekrutmen berhasil disimpan.',
            'data' => $recruitmentRequest->fresh(['requester', 'vacancy', 'replacedEmployee', 'directReport', 'picHiring']),
        ]);
    }

    public function updateHiring(Request $request, RecruitmentRequest $recruitmentRequest): JsonResponse
    {
        $payload = $request->validate([
            'hiring_status' => ['required', 'in:pending,accepted,rejected,ongoing,hired,canceled'],
            'pic_hiring_nik' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'finished_date' => ['nullable', 'date'],
            'hrd_notes' => ['nullable', 'string'],
            'vacancy_link_mode' => ['nullable', 'in:none,existing,new'],
            'vacancy_id' => ['nullable', 'exists:recruitment_vacancies,id'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($recruitmentRequest);

        $updateData = [
            'hiring_status' => $payload['hiring_status'],
            'start_date' => $payload['start_date'] ?? $recruitmentRequest->start_date,
            'finished_date' => $payload['finished_date'] ?? $recruitmentRequest->finished_date,
            'hrd_notes' => $payload['hrd_notes'] ?? $recruitmentRequest->hrd_notes,
        ];

        if (! empty($payload['vacancy_link_mode'])) {
            if ($payload['vacancy_link_mode'] === 'new') {
                $vacancy = RecruitmentVacancy::query()->create([
                    'title' => $recruitmentRequest->title,
                    'department' => $recruitmentRequest->department,
                    'unit' => $recruitmentRequest->unit,
                    'status' => 'open',
                ]);
                $updateData['vacancy_id'] = $vacancy->id;
            } elseif ($payload['vacancy_link_mode'] === 'existing' && ! empty($payload['vacancy_id'])) {
                $updateData['vacancy_id'] = $payload['vacancy_id'];
            }
        }

        if (array_key_exists('pic_hiring_nik', $payload)) {
            if (! empty($payload['pic_hiring_nik'])) {
                $pic = Karyawan::where('nik', $payload['pic_hiring_nik'])->first();
                $updateData['pic_hiring_nik'] = $payload['pic_hiring_nik'];
                $updateData['pic_hiring_name'] = $pic?->nama_karyawan;
            } else {
                $updateData['pic_hiring_nik'] = null;
                $updateData['pic_hiring_name'] = null;
            }
        }

        if ($payload['hiring_status'] === 'hired' && empty($updateData['finished_date'])) {
            $updateData['finished_date'] = now()->toDateString();
        }

        $recruitmentRequest->update($updateData);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentRequest',
            'updated_hiring',
            "Recruitment Request #{$recruitmentRequest->id} hiring status updated to {$recruitmentRequest->hiring_status}",
            $beforeAudit,
            $recruitmentRequest->fresh(),
            RecruitmentRequest::class,
            $recruitmentRequest->id
        );

        return response()->json([
            'message' => 'Status operasional rekrutmen berhasil diperbarui.',
            'data' => $recruitmentRequest->fresh(['requester', 'vacancy', 'replacedEmployee', 'directReport', 'picHiring']),
        ]);
    }
}
