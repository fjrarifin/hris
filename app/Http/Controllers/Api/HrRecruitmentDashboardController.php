<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrdAuditLog;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentRequest;
use App\Models\RecruitmentVacancy;
use App\Services\RecruitmentStageService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class HrRecruitmentDashboardController extends Controller
{
    private const STAGE_LABELS = [
        'applied' => 'Applied',
        'screening' => 'Screening',
        'interview_hr' => 'Wawancara HR',
        'case_study' => 'Case Study',
        'interview_user' => 'Wawancara User',
        'reference_check' => 'Reference Check',
        'offering' => 'Offering',
        'pkb' => 'PKB',
        'hired' => 'Hired & Onboarding',
        'rejected' => 'Rejected',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'vacancy_id' => ['nullable', 'integer', 'exists:recruitment_vacancies,id'],
            'department' => ['nullable', 'string', 'max:100'],
            'unit' => ['nullable', 'string', 'max:100'],
            'pic_nik' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(RecruitmentStageService::STAGES)],
            'action_full' => ['nullable', 'boolean'],
        ]);

        if ($filters['month'] ?? null) {
            $selectedMonth = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
            $startDate = $selectedMonth->copy()->startOfDay();
            $endDate = $selectedMonth->copy()->endOfMonth()->endOfDay();
        } elseif (($filters['start_date'] ?? null) || ($filters['end_date'] ?? null)) {
            // Tetap menerima filter tanggal lama untuk kompatibilitas API.
            $endDate = Carbon::parse($filters['end_date'] ?? $filters['start_date'])->endOfDay();
            $startDate = Carbon::parse($filters['start_date'] ?? $filters['end_date'])->startOfDay();
        } else {
            $startDate = today()->startOfMonth()->startOfDay();
            $endDate = today()->endOfMonth()->endOfDay();
        }

        $trendEndDate = $startDate->isSameMonth(today())
            ? today()->endOfDay()
            : $endDate;

        $candidates = RecruitmentCandidate::query()
            ->with([
                'vacancy:id,title,department,unit,status,created_at',
                'pic:nik,nama_karyawan',
                'userInterviews:id,candidate_id,round,interview_date,interview_time,completed_at,summary_path',
                'userInterviewEvaluations:id,candidate_id,round,interviewer_nik,sent_at,interview_total_score,interview_recommendation,interview_appearance,interview_attitude,interview_communication,interview_motivation,interview_initiative,interview_teamwork,interview_domain_experience,interview_general_knowledge,interview_growth_potential,submitted_at',
                'userInterviewEvaluations.interviewer:nik,nama_karyawan',
                'pkbSigners:id,candidate_id,employee_nik,sent_at,signed_at',
                'stageHistories:id,candidate_id,stage,entered_at,exited_at',
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($filters['vacancy_id'] ?? null, fn (Builder $query, $value) => $query->where('vacancy_id', $value))
            ->when($filters['pic_nik'] ?? null, fn (Builder $query, $value) => $query->where('pic_nik', $value))
            ->when($filters['source'] ?? null, fn (Builder $query, $value) => $query->where('referred_from', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, $value) => $query->where('status', $value))
            ->when($filters['department'] ?? null, fn (Builder $query, $value) => $query->whereHas('vacancy', fn (Builder $vacancy) => $vacancy->where('department', $value)))
            ->when($filters['unit'] ?? null, fn (Builder $query, $value) => $query->whereHas('vacancy', fn (Builder $vacancy) => $vacancy->where('unit', $value)))
            ->get();

        $candidateIds = $candidates->pluck('id');
        $actions = $this->actionCenter($candidates, ! ($filters['action_full'] ?? false));

        return response()->json([
            'period' => [
                'month' => $startDate->format('Y-m'),
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
                'generated_at' => now()->toIso8601String(),
            ],
            'summary' => $this->summary($candidates, $actions, $startDate, $endDate, $filters),
            'pipeline' => $this->pipeline($candidates),
            'trend' => $this->trend($candidates, $startDate, $trendEndDate),
            'stage_durations' => $this->stageDurations($candidates),
            'action_center' => $actions,
            'upcoming_schedules' => $this->upcomingSchedules($candidates),
            'document_progress' => $this->documentProgress($candidates),
            'vacancy_performance' => $this->vacancyPerformance($candidates, $filters),
            'interview_quality' => $this->interviewQuality($candidates),
            'offering_onboarding' => $this->offeringAndOnboarding($candidates),
            'sources' => $this->sourceDistribution($candidates),
            'recent_activities' => $this->recentActivities($candidateIds),
            'filter_options' => $this->filterOptions(),
        ]);
    }

    private function summary(Collection $candidates, array $actions, Carbon $start, Carbon $end, array $filters): array
    {
        $active = $candidates->whereNotIn('status', ['hired', 'rejected'])->count();
        $hired = $candidates->where('status', 'hired')->count();
        $resolved = $hired + $candidates->where('status', 'rejected')->count();

        $vacancyQuery = RecruitmentVacancy::query()->where('status', 'open');
        $this->applyVacancyFilters($vacancyQuery, $filters);

        $requestQuery = RecruitmentRequest::query()->where('status', 'approved');
        if ($filters['vacancy_id'] ?? null) {
            $requestQuery->where('vacancy_id', $filters['vacancy_id']);
        }
        if ($filters['department'] ?? null) {
            $requestQuery->where('department', $filters['department']);
        }
        if ($filters['unit'] ?? null) {
            $requestQuery->where('unit', $filters['unit']);
        }

        return [
            'total_candidates' => $candidates->count(),
            'active_candidates' => $active,
            'new_candidates' => $candidates->whereBetween('created_at', [$start, $end])->count(),
            'open_vacancies' => $vacancyQuery->count(),
            'approved_headcount' => (int) $requestQuery->sum('quantity'),
            'hired_candidates' => $hired,
            'hire_rate' => $resolved > 0 ? round(($hired / $resolved) * 100, 1) : 0,
            'pending_actions' => collect($actions)->sum('count'),
            'joining_in_period' => $candidates
                ->filter(fn (RecruitmentCandidate $candidate) => $candidate->join_date && Carbon::parse($candidate->join_date)->between($start, $end))
                ->count(),
            // Alias lama agar consumer API sebelumnya tidak langsung rusak.
            'joining_soon' => $candidates
                ->filter(fn (RecruitmentCandidate $candidate) => $candidate->join_date && Carbon::parse($candidate->join_date)->between($start, $end))
                ->count(),
        ];
    }

    private function pipeline(Collection $candidates): array
    {
        $stages = array_values(array_filter(RecruitmentStageService::STAGES, fn (string $stage) => $stage !== 'rejected'));

        return collect($stages)->map(function (string $stage, int $index) use ($candidates, $stages): array {
            $current = $candidates->where('status', $stage)->count();
            $reached = $candidates->filter(function (RecruitmentCandidate $candidate) use ($stage, $index, $stages): bool {
                if ($candidate->stageHistories->contains('stage', $stage)) {
                    return true;
                }

                $currentIndex = array_search($candidate->status, $stages, true);

                return $currentIndex !== false && $currentIndex >= $index;
            })->count();

            return [
                'key' => $stage,
                'label' => self::STAGE_LABELS[$stage],
                'current' => $current,
                'reached' => $reached,
                'conversion' => $index === 0 || $candidates->count() === 0
                    ? ($candidates->count() ? 100 : 0)
                    : round(($reached / max(1, $candidates->count())) * 100, 1),
            ];
        })->all();
    }

    private function trend(Collection $candidates, Carbon $start, Carbon $end): array
    {
        $useMonths = $start->diffInDays($end) > 62;
        $format = $useMonths ? 'Y-m' : 'Y-m-d';
        $cursor = $start->copy()->startOf($useMonths ? 'month' : 'day');
        $finish = $end->copy()->startOf($useMonths ? 'month' : 'day');
        $buckets = [];

        while ($cursor->lte($finish)) {
            $key = $cursor->format($format);
            $buckets[$key] = ['period' => $key, 'applied' => 0, 'hired' => 0];
            $useMonths ? $cursor->addMonth() : $cursor->addDay();
        }

        foreach ($candidates as $candidate) {
            $key = Carbon::parse($candidate->created_at)->format($format);
            if (isset($buckets[$key])) {
                $buckets[$key]['applied']++;
            }

            $hiredAt = $candidate->stageHistories->firstWhere('stage', 'hired')?->entered_at;
            if ($hiredAt) {
                $hiredKey = Carbon::parse($hiredAt)->format($format);
                if (isset($buckets[$hiredKey])) {
                    $buckets[$hiredKey]['hired']++;
                }
            }
        }

        return array_values($buckets);
    }

    private function stageDurations(Collection $candidates): array
    {
        return $candidates
            ->flatMap->stageHistories
            ->whereNotNull('exited_at')
            ->groupBy('stage')
            ->map(fn (Collection $items, string $stage) => [
                'key' => $stage,
                'label' => self::STAGE_LABELS[$stage] ?? $stage,
                'average_hours' => round($items->avg(fn ($item) => $item->entered_at->diffInMinutes($item->exited_at) / 60), 1),
                'samples' => $items->count(),
            ])
            ->values()
            ->all();
    }

    private function actionCenter(Collection $candidates, bool $limitCandidates = true): array
    {
        $definitions = [
            'hr_interview_schedule' => ['label' => 'Interview HR belum dijadwalkan', 'icon' => 'i-lucide-calendar-clock', 'color' => 'purple', 'filter' => fn ($c) => $c->status === 'interview_hr' && ! $c->interview_hr_date],
            'hr_interview_completion' => ['label' => 'Interview HR belum ditandai selesai', 'icon' => 'i-lucide-circle-check-big', 'color' => 'purple', 'filter' => fn ($c) => $c->status === 'interview_hr' && $c->interview_hr_date && $c->interview_hr_time && ! $c->interview_hr_completed_at && Carbon::parse($c->interview_hr_date.' '.$c->interview_hr_time)->addHour()->lte(now())],
            'hr_summary' => ['label' => 'Summary wawancara HR belum tersedia', 'icon' => 'i-lucide-file-warning', 'color' => 'purple', 'filter' => fn ($c) => $this->atOrAfter($c, 'interview_hr') && ! $c->interview_hr_summary_path && ! $c->interview_hr_text_summary],
            'case_delivery' => ['label' => 'Case study belum dikirim', 'icon' => 'i-lucide-send', 'color' => 'orange', 'filter' => fn ($c) => $c->status === 'case_study' && ! $c->case_study_sent_at],
            'case_submission' => ['label' => 'Jawaban case study belum dikumpulkan', 'icon' => 'i-lucide-clipboard-clock', 'color' => 'orange', 'filter' => fn ($c) => $c->status === 'case_study' && $c->case_study_sent_at && ! $c->case_study_submitted_file_path],
            'user_interview_schedule' => ['label' => 'Interview User Tahap #1 belum dijadwalkan', 'icon' => 'i-lucide-calendar-plus-2', 'color' => 'indigo', 'filter' => fn ($c) => $c->status === 'interview_user' && $c->userInterviews->isEmpty(), 'detail' => fn () => 'Belum ada jadwal Interview User'],
            'user_interview_completion' => ['label' => 'Interview User belum ditandai selesai', 'icon' => 'i-lucide-circle-check-big', 'color' => 'indigo', 'filter' => fn ($c) => $c->status === 'interview_user' && $this->overdueUserInterviewRounds($c)->isNotEmpty(), 'detail' => fn ($c) => $this->roundDetail($this->overdueUserInterviewRounds($c))],
            'user_evaluation_link' => ['label' => 'Link evaluasi belum dikirim', 'icon' => 'i-lucide-send-horizontal', 'color' => 'indigo', 'filter' => fn ($c) => $c->status === 'interview_user' && $this->userEvaluationRoundsByState($c, 'unsent')->isNotEmpty(), 'detail' => fn ($c) => $this->evaluationActionDetail($c, 'unsent')],
            'user_evaluation_waiting' => ['label' => 'Pewawancara belum submit evaluasi', 'icon' => 'i-lucide-hourglass', 'color' => 'indigo', 'filter' => fn ($c) => $c->status === 'interview_user' && $this->userEvaluationRoundsByState($c, 'waiting')->isNotEmpty(), 'detail' => fn ($c) => $this->evaluationActionDetail($c, 'waiting')],
            'user_evaluation_partial' => ['label' => 'Evaluasi pewawancara belum lengkap', 'icon' => 'i-lucide-users-round', 'color' => 'indigo', 'filter' => fn ($c) => $c->status === 'interview_user' && $this->userEvaluationRoundsByState($c, 'partial')->isNotEmpty(), 'detail' => fn ($c) => $this->evaluationActionDetail($c, 'partial')],
            'reference_request' => ['label' => 'Permintaan reference check belum dikirim', 'icon' => 'i-lucide-send', 'color' => 'teal', 'filter' => fn ($c) => $c->status === 'reference_check' && ! $c->reference_check_token],
            'reference_response' => ['label' => 'Kandidat belum mengisi reference check', 'icon' => 'i-lucide-phone-missed', 'color' => 'teal', 'filter' => fn ($c) => $c->status === 'reference_check' && $c->reference_check_token && ! $c->reference_check_submitted_at],
            'reference_summary' => ['label' => 'Summary reference check belum tersedia', 'icon' => 'i-lucide-file-warning', 'color' => 'teal', 'filter' => fn ($c) => $c->status === 'reference_check' && $c->reference_check_submitted_at && ! $c->reference_check_summary_path],
            'offering_delivery' => ['label' => 'Offering letter belum dikirim', 'icon' => 'i-lucide-mail-plus', 'color' => 'pink', 'filter' => fn ($c) => $c->status === 'offering' && (! $c->offering_letter_path || ! $c->offering_letter_sent_at)],
            'offering_signature' => ['label' => 'Offering belum ditandatangani kandidat', 'icon' => 'i-lucide-file-signature', 'color' => 'pink', 'filter' => fn ($c) => $c->status === 'offering' && $c->offering_letter_path && $c->offering_letter_sent_at && ! $c->offering_letter_signed_at],
            'pkb_request' => ['label' => 'Permintaan persetujuan PKB belum dikirim', 'icon' => 'i-lucide-file-plus-2', 'color' => 'amber', 'filter' => fn ($c) => $c->status === 'pkb' && $c->pkbSigners->isEmpty()],
            'pkb_approval' => ['label' => 'Penyetuju PKB belum lengkap', 'icon' => 'i-lucide-stamp', 'color' => 'amber', 'filter' => fn ($c) => $c->status === 'pkb' && $c->pkbSigners->isNotEmpty() && $c->pkbSigners->contains(fn ($signer) => ! $signer->signed_at)],
            'onboarding_link' => ['label' => 'Link onboarding belum dikirim', 'icon' => 'i-lucide-link-2', 'color' => 'emerald', 'filter' => fn ($c) => $c->status === 'hired' && ! $c->onboarding_sent_at],
            'onboarding_form' => ['label' => 'Kandidat belum menyelesaikan onboarding', 'icon' => 'i-lucide-clipboard-clock', 'color' => 'emerald', 'filter' => fn ($c) => $c->status === 'hired' && $c->onboarding_sent_at && ! $c->onboarding_completed_at],
            'onboarding_import' => ['label' => 'Data onboarding belum diimport', 'icon' => 'i-lucide-user-round-plus', 'color' => 'emerald', 'filter' => fn ($c) => $c->status === 'hired' && $c->onboarding_completed_at && ! $c->employee_nik],
            'inactive' => ['label' => 'Tidak ada aktivitas lebih dari 3 hari', 'icon' => 'i-lucide-timer-off', 'color' => 'red', 'filter' => fn ($c) => ! in_array($c->status, ['hired', 'rejected'], true) && Carbon::parse($c->updated_at)->lt(now()->subDays(3))],
        ];

        return collect($definitions)->map(function (array $definition, string $key) use ($candidates, $limitCandidates): array {
            $matches = $candidates->filter($definition['filter']);
            $displayedMatches = $limitCandidates ? $matches->take(6) : $matches;

            return [
                'key' => $key,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'color' => $definition['color'],
                'count' => $matches->count(),
                'candidates' => $displayedMatches->map(function ($candidate) use ($definition): array {
                    return [
                        ...$this->candidateSummary($candidate),
                        'detail' => isset($definition['detail']) ? $definition['detail']($candidate) : null,
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }

    private function overdueUserInterviewRounds(RecruitmentCandidate $candidate): Collection
    {
        return $candidate->userInterviews
            ->filter(fn ($interview) => $interview->interview_date
                && $interview->interview_time
                && ! $interview->completed_at
                && Carbon::parse($interview->interview_date.' '.$interview->interview_time)->addHour()->lte(now()))
            ->pluck('round')
            ->sort()
            ->values();
    }

    private function userEvaluationRoundsByState(RecruitmentCandidate $candidate, string $state): Collection
    {
        $completedRounds = $candidate->userInterviews->whereNotNull('completed_at')->pluck('round');

        return $candidate->userInterviewEvaluations
            ->filter(fn ($evaluation) => $completedRounds->contains($evaluation->round))
            ->groupBy('round')
            ->filter(function (Collection $evaluations) use ($state): bool {
                $submitted = $evaluations->whereNotNull('submitted_at');
                $pending = $evaluations->whereNull('submitted_at');

                return match ($state) {
                    'unsent' => $pending->contains(fn ($evaluation) => ! $evaluation->sent_at),
                    'waiting' => $submitted->isEmpty() && $pending->contains(fn ($evaluation) => (bool) $evaluation->sent_at),
                    'partial' => $submitted->isNotEmpty() && $pending->isNotEmpty(),
                    default => false,
                };
            })
            ->keys()
            ->sort()
            ->values();
    }

    private function evaluationActionDetail(RecruitmentCandidate $candidate, string $state): string
    {
        $rounds = $this->userEvaluationRoundsByState($candidate, $state);
        $pendingNames = $candidate->userInterviewEvaluations
            ->filter(fn ($evaluation) => $rounds->contains($evaluation->round) && ! $evaluation->submitted_at)
            ->map(fn ($evaluation) => $evaluation->interviewer?->nama_karyawan ?: $evaluation->interviewer_nik)
            ->filter()
            ->unique()
            ->take(2)
            ->implode(', ');

        return $this->roundDetail($rounds).($pendingNames ? ' • '.$pendingNames : '');
    }

    private function roundDetail(Collection $rounds): string
    {
        return $rounds->map(fn ($round) => "Tahap #{$round}")->implode(', ');
    }

    private function upcomingSchedules(Collection $candidates): array
    {
        $from = now()->startOfDay();
        $until = now()->addDays(7)->endOfDay();
        $items = collect();

        foreach ($candidates as $candidate) {
            if ($candidate->interview_hr_date && $candidate->interview_hr_time && ! $candidate->interview_hr_completed_at) {
                $at = Carbon::parse(Carbon::parse($candidate->interview_hr_date)->toDateString().' '.$candidate->interview_hr_time);
                if ($at->between($from, $until)) {
                    $items->push([...$this->candidateSummary($candidate), 'type' => 'Wawancara HR', 'scheduled_at' => $at->toIso8601String()]);
                }
            }

            foreach ($candidate->userInterviews as $interview) {
                if (! $interview->interview_date || ! $interview->interview_time) {
                    continue;
                }
                if ($interview->completed_at) {
                    continue;
                }
                $at = Carbon::parse($interview->interview_date.' '.$interview->interview_time);
                if ($at->between($from, $until)) {
                    $items->push([...$this->candidateSummary($candidate), 'type' => "Wawancara User R{$interview->round}", 'scheduled_at' => $at->toIso8601String()]);
                }
            }
        }

        return $items->sortBy('scheduled_at')->take(4)->values()->all();
    }

    private function documentProgress(Collection $candidates): array
    {
        $definitions = [
            ['key' => 'resume', 'label' => 'CV Kandidat', 'stage' => 'applied', 'complete' => fn ($c) => (bool) $c->resume_path],
            ['key' => 'hr_summary', 'label' => 'Summary Wawancara HR', 'stage' => 'interview_hr', 'complete' => fn ($c) => (bool) ($c->interview_hr_summary_path || $c->interview_hr_text_summary)],
            ['key' => 'case_submission', 'label' => 'Hasil Case Study', 'stage' => 'case_study', 'complete' => fn ($c) => (bool) $c->case_study_submitted_file_path],
            ['key' => 'user_summary', 'label' => 'Summary Wawancara User', 'stage' => 'interview_user', 'complete' => fn ($c) => $c->userInterviews->contains(fn ($item) => (bool) $item->summary_path)],
            ['key' => 'user_evaluation', 'label' => 'Evaluasi Wawancara User', 'stage' => 'interview_user', 'complete' => fn ($c) => $c->userInterviewEvaluations->isNotEmpty() && $c->userInterviewEvaluations->every(fn ($item) => (bool) $item->submitted_at)],
            ['key' => 'reference_summary', 'label' => 'Summary Reference Check', 'stage' => 'reference_check', 'complete' => fn ($c) => (bool) $c->reference_check_summary_path],
            ['key' => 'offering_letter', 'label' => 'Offering Letter Ditandatangani', 'stage' => 'offering', 'complete' => fn ($c) => (bool) $c->offering_letter_signed_at],
            ['key' => 'pkb', 'label' => 'Persetujuan PKB', 'stage' => 'pkb', 'complete' => fn ($c) => $c->pkbSigners->isNotEmpty() && $c->pkbSigners->every(fn ($item) => (bool) $item->signed_at)],
            ['key' => 'onboarding', 'label' => 'Onboarding & Import Karyawan', 'stage' => 'hired', 'complete' => fn ($c) => (bool) ($c->onboarding_completed_at && $c->employee_nik)],
        ];

        return collect($definitions)->map(function (array $definition) use ($candidates): array {
            $eligible = $candidates->filter(fn ($candidate) => $this->atOrAfter($candidate, $definition['stage']));
            $complete = $eligible->filter($definition['complete'])->count();

            return [
                'key' => $definition['key'],
                'label' => $definition['label'],
                'eligible' => $eligible->count(),
                'complete' => $complete,
                'missing' => $eligible->count() - $complete,
                'percentage' => $eligible->count() ? round(($complete / $eligible->count()) * 100, 1) : 0,
            ];
        })->all();
    }

    private function vacancyPerformance(Collection $candidates, array $filters): array
    {
        $query = RecruitmentVacancy::query()->with('candidates:id,vacancy_id,status,created_at');
        $this->applyVacancyFilters($query, $filters);

        $targets = RecruitmentRequest::query()
            ->where('status', 'approved')
            ->whereNotNull('vacancy_id')
            ->selectRaw('vacancy_id, SUM(quantity) target')
            ->groupBy('vacancy_id')
            ->pluck('target', 'vacancy_id');

        return $query->get()->map(function (RecruitmentVacancy $vacancy) use ($candidates, $targets): array {
            $items = $candidates->where('vacancy_id', $vacancy->id);
            $target = (int) ($targets[$vacancy->id] ?? 0);
            $hired = $items->where('status', 'hired')->count();

            return [
                'id' => $vacancy->id,
                'title' => $vacancy->title,
                'department' => $vacancy->department,
                'unit' => $vacancy->unit,
                'status' => $vacancy->status,
                'target' => $target,
                'candidates' => $items->count(),
                'active' => $items->whereNotIn('status', ['hired', 'rejected'])->count(),
                'offering' => $items->where('status', 'offering')->count(),
                'hired' => $hired,
                'fulfillment' => $target ? round(($hired / $target) * 100, 1) : null,
                'age_days' => Carbon::parse($vacancy->created_at)->diffInDays(today()),
            ];
        })->sortByDesc('candidates')->values()->all();
    }

    private function interviewQuality(Collection $candidates): array
    {
        $evaluations = $candidates->flatMap(function (RecruitmentCandidate $candidate): Collection {
            $completedRounds = $candidate->userInterviews->whereNotNull('completed_at')->pluck('round');

            return $candidate->userInterviewEvaluations
                ->filter(fn ($evaluation) => $completedRounds->contains($evaluation->round));
        });
        $submitted = $evaluations->whereNotNull('submitted_at');
        $submittedCandidateIds = $submitted->pluck('candidate_id')->unique();
        $evaluatedCandidates = $candidates
            ->whereIn('id', $submittedCandidateIds)
            ->map(function (RecruitmentCandidate $candidate): array {
                $candidateEvaluations = $candidate->userInterviewEvaluations->whereNotNull('submitted_at');

                return [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'vacancy' => $candidate->vacancy?->title ?: 'Umum',
                    'evaluation_count' => $candidateEvaluations->count(),
                    'average_score' => round((float) $candidateEvaluations->avg('interview_total_score'), 1),
                ];
            })
            ->values();
        $aspects = [
            'interview_appearance' => 'Penampilan',
            'interview_attitude' => 'Sikap',
            'interview_communication' => 'Komunikasi',
            'interview_motivation' => 'Motivasi',
            'interview_initiative' => 'Inisiatif',
            'interview_teamwork' => 'Kerja Sama',
            'interview_domain_experience' => 'Pengalaman Bidang',
            'interview_general_knowledge' => 'Pengetahuan Umum',
            'interview_growth_potential' => 'Potensi Berkembang',
        ];

        return [
            'total' => $evaluations->count(),
            'submitted' => $submitted->count(),
            'pending' => $evaluations->count() - $submitted->count(),
            'candidate_count' => $submittedCandidateIds->count(),
            'interviewer_count' => $submitted->pluck('interviewer_nik')->filter()->unique()->count(),
            'candidates' => $evaluatedCandidates->take(5)->all(),
            'completion_rate' => $evaluations->count() ? round(($submitted->count() / $evaluations->count()) * 100, 1) : 0,
            'average_total_score' => $submitted->whereNotNull('interview_total_score')->count()
                ? round($submitted->avg('interview_total_score'), 1)
                : 0,
            'recommendations' => collect(['disarankan', 'dipertimbangkan', 'tidak_disarankan'])->map(fn ($key) => [
                'key' => $key,
                'count' => $submitted->where('interview_recommendation', $key)->count(),
            ])->all(),
            'aspects' => collect($aspects)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'average' => round((float) $submitted->whereNotNull($key)->avg($key), 2),
            ])->values()->all(),
        ];
    }

    private function offeringAndOnboarding(Collection $candidates): array
    {
        $offerEligible = $candidates->filter(fn ($candidate) => $this->atOrAfter($candidate, 'offering'));
        $offersSent = $offerEligible->whereNotNull('offering_letter_sent_at');
        $offersSigned = $offerEligible->whereNotNull('offering_letter_signed_at');
        $pkbSigners = $candidates->flatMap->pkbSigners;

        return [
            'offering' => [
                'eligible' => $offerEligible->count(),
                'sent' => $offersSent->count(),
                'signed' => $offersSigned->count(),
                'pending_signature' => $offerEligible->filter(fn ($candidate) => $candidate->offering_letter_path && ! $candidate->offering_letter_signed_at)->count(),
                'acceptance_rate' => $offersSent->count() ? round(($offersSigned->count() / $offersSent->count()) * 100, 1) : 0,
                'average_sign_hours' => round((float) $offersSigned->avg(fn ($candidate) => $candidate->offering_letter_sent_at
                    ? Carbon::parse($candidate->offering_letter_sent_at)->diffInMinutes($candidate->offering_letter_signed_at) / 60
                    : null), 1),
            ],
            'pkb' => [
                'signers' => $pkbSigners->count(),
                'signed' => $pkbSigners->whereNotNull('signed_at')->count(),
                'pending' => $pkbSigners->whereNull('signed_at')->count(),
            ],
            'onboarding' => [
                'hired' => $candidates->where('status', 'hired')->count(),
                'link_sent' => $candidates->whereNotNull('onboarding_sent_at')->count(),
                'completed' => $candidates->whereNotNull('onboarding_completed_at')->count(),
                'imported' => $candidates->whereNotNull('employee_nik')->count(),
            ],
        ];
    }

    private function sourceDistribution(Collection $candidates): array
    {
        return $candidates
            ->groupBy(fn ($candidate) => trim((string) $candidate->referred_from) ?: 'Tidak tercatat')
            ->map(fn (Collection $items, string $source) => [
                'source' => $source,
                'count' => $items->count(),
                'hired' => $items->where('status', 'hired')->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function recentActivities(Collection $candidateIds): array
    {
        if ($candidateIds->isEmpty()) {
            return [];
        }

        return HrdAuditLog::query()
            ->where('subject_type', RecruitmentCandidate::class)
            ->whereIn('subject_id', $candidateIds->map(fn ($id) => (string) $id))
            ->latest('occurred_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'candidate_id' => (int) $log->subject_id,
                'subject' => $log->subject_label,
                'action' => $log->action,
                'actor' => $log->actor_name ?: $log->actor_username ?: 'Sistem',
                'occurred_at' => optional($log->occurred_at ?: $log->created_at)->toIso8601String(),
            ])
            ->all();
    }

    private function filterOptions(): array
    {
        return [
            'vacancies' => RecruitmentVacancy::query()->orderBy('title')->get(['id', 'title', 'department', 'unit', 'status']),
            'departments' => RecruitmentVacancy::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'units' => RecruitmentVacancy::query()->whereNotNull('unit')->distinct()->orderBy('unit')->pluck('unit'),
            'pics' => RecruitmentCandidate::query()->with('pic:nik,nama_karyawan')->whereNotNull('pic_nik')->get()->map(fn ($candidate) => [
                'nik' => $candidate->pic_nik,
                'name' => $candidate->pic?->nama_karyawan ?: $candidate->pic_nik,
            ])->unique('nik')->values(),
            'sources' => RecruitmentCandidate::query()->whereNotNull('referred_from')->where('referred_from', '<>', '')->distinct()->orderBy('referred_from')->pluck('referred_from'),
        ];
    }

    private function candidateSummary(RecruitmentCandidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'stage' => $candidate->status,
            'vacancy' => $candidate->vacancy?->title ?: 'Umum',
            'pic' => $candidate->pic?->nama_karyawan ?: $candidate->pic_nik,
            'updated_at' => optional($candidate->updated_at)->toIso8601String(),
        ];
    }

    private function atOrAfter(RecruitmentCandidate $candidate, string $stage): bool
    {
        if ($candidate->stageHistories->contains('stage', $stage)) {
            return true;
        }

        $stages = array_values(array_filter(RecruitmentStageService::STAGES, fn ($value) => $value !== 'rejected'));
        $current = array_search($candidate->status, $stages, true);
        $target = array_search($stage, $stages, true);

        return $current !== false && $target !== false && $current >= $target;
    }

    private function applyVacancyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['vacancy_id'] ?? null, fn (Builder $builder, $value) => $builder->whereKey($value))
            ->when($filters['department'] ?? null, fn (Builder $builder, $value) => $builder->where('department', $value))
            ->when($filters['unit'] ?? null, fn (Builder $builder, $value) => $builder->where('unit', $value));
    }
}
