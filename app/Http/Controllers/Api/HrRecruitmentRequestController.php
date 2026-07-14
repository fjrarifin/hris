<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentRequest;
use App\Models\RecruitmentVacancy;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrRecruitmentRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RecruitmentRequest::query()
                ->with(['requester', 'vacancy'])
                ->latest()
                ->get()
        );
    }

    public function decide(Request $request, RecruitmentRequest $recruitmentRequest): JsonResponse
    {
        $payload = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'hrd_notes' => ['nullable', 'string'],
            'vacancy_link_mode' => ['nullable', 'in:none,existing,new'],
            'vacancy_id' => ['nullable', 'exists:recruitment_vacancies,id'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($recruitmentRequest);

        $updateData = [
            'status' => $payload['status'],
            'hrd_notes' => $payload['hrd_notes'] ?? null,
        ];

        if ($payload['status'] === 'approved') {
            $mode = $payload['vacancy_link_mode'] ?? 'none';
            if ($mode === 'new') {
                $vacancy = RecruitmentVacancy::query()->create([
                    'title' => $recruitmentRequest->title,
                    'department' => $recruitmentRequest->department,
                    'unit' => $recruitmentRequest->unit,
                    'status' => 'open',
                ]);
                $updateData['vacancy_id'] = $vacancy->id;
            } elseif ($mode === 'existing' && !empty($payload['vacancy_id'])) {
                $updateData['vacancy_id'] = $payload['vacancy_id'];
            }
        }

        $recruitmentRequest->update($updateData);

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
            'data' => $recruitmentRequest->load(['requester', 'vacancy']),
        ]);
    }
}
