<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecruitmentVacancy;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class HrRecruitmentVacancyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            RecruitmentVacancy::query()
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->latest()
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,open,closed'],
        ]);

        $vacancy = RecruitmentVacancy::query()->create($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentVacancy',
            'created',
            "Vacancy #{$vacancy->id}: {$vacancy->title}",
            null,
            $vacancy,
            RecruitmentVacancy::class,
            $vacancy->id
        );

        return response()->json(['message' => 'Lowongan berhasil dibuat.', 'data' => $vacancy], 201);
    }

    public function update(Request $request, RecruitmentVacancy $vacancy): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:draft,open,closed'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($vacancy);
        $vacancy->update($payload);

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentVacancy',
            'updated',
            "Vacancy #{$vacancy->id}: {$vacancy->title}",
            $beforeAudit,
            $vacancy->fresh(),
            RecruitmentVacancy::class,
            $vacancy->id
        );

        return response()->json(['message' => 'Lowongan berhasil diperbarui.', 'data' => $vacancy]);
    }

    public function show(RecruitmentVacancy $vacancy): JsonResponse
    {
        return response()->json($vacancy->load('candidates'));
    }

    public function destroy(Request $request, RecruitmentVacancy $vacancy): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($vacancy);
        $subjectId = $vacancy->id;
        $subjectLabel = "Vacancy #{$vacancy->id}: {$vacancy->title}";

        $vacancy->delete();

        app(HrdAuditLogService::class)->record(
            $request,
            'RecruitmentVacancy',
            'deleted',
            $subjectLabel,
            $beforeAudit,
            null,
            RecruitmentVacancy::class,
            $subjectId
        );

        return response()->json(['message' => 'Lowongan berhasil dihapus.']);
    }
}
