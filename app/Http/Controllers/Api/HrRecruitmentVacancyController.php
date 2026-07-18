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
                ->withCount('candidates')
                ->withCount(['candidates as hired_candidates_count' => function ($query) {
                    $query->where('status', 'hired');
                }])
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
                ->latest()
                ->get()
        );
    }

    public function favorite(Request $request): JsonResponse
    {
        $month = $request->query('month');

        $vacancies = RecruitmentVacancy::query()
            ->with(['candidates' => function ($query) use ($month) {
                if ($month) {
                    $query->where('created_at', 'like', "{$month}%");
                }
            }])
            ->get()
            ->map(function ($vacancy) {
                $totalApplied = $vacancy->candidates->count();
                if ($totalApplied === 0) {
                    return null;
                }

                $candidateIds = $vacancy->candidates->pluck('id');
                $passedCount = \App\Models\RecruitmentCandidateStageHistory::query()
                    ->whereIn('candidate_id', $candidateIds)
                    ->where('stage', 'interview_hr')
                    ->distinct('candidate_id')
                    ->count('candidate_id');

                $percentage = $totalApplied > 0 ? round(($passedCount / $totalApplied) * 100) : 0;

                return [
                    'id' => $vacancy->id,
                    'title' => $vacancy->title,
                    'department' => $vacancy->department,
                    'unit' => $vacancy->unit,
                    'division' => $vacancy->division,
                    'total_applied' => $totalApplied,
                    'passed_count' => $passedCount,
                    'percentage' => $percentage,
                ];
            })
            ->filter()
            ->sortByDesc('total_applied')
            ->take(5)
            ->values();

        return response()->json($vacancies);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'division' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'supervisor_nik' => ['nullable', 'string', 'max:30'],
            'supervisor_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,internship,temporary'],
            'workplace_type' => ['nullable', 'in:onsite,hybrid,remote'],
            'location' => ['nullable', 'string', 'max:150'],
            'responsibilities' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'application_deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,open,closed'],
        ]);

        if ($payload['status'] === 'open' && empty($payload['published_at'])) {
            $payload['published_at'] = now();
        }

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
            'division' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'string', 'max:100'],
            'supervisor_nik' => ['nullable', 'string', 'max:30'],
            'supervisor_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'employment_type' => ['nullable', 'in:full_time,part_time,contract,internship,temporary'],
            'workplace_type' => ['nullable', 'in:onsite,hybrid,remote'],
            'location' => ['nullable', 'string', 'max:150'],
            'responsibilities' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
            'application_deadline' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,open,closed'],
        ]);

        if ($payload['status'] === 'open' && empty($payload['published_at'])) {
            $payload['published_at'] = $vacancy->published_at ?: now();
        }

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
