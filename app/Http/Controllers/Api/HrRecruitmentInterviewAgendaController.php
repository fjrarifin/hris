<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Models\RecruitmentCandidate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class HrRecruitmentInterviewAgendaController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in(['hr', 'user'])],
            'status' => ['nullable', Rule::in(['upcoming', 'overdue', 'completed'])],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $from = Carbon::parse($filters['from'] ?? today()->startOfMonth())->startOfDay();
        $to = Carbon::parse($filters['to'] ?? today()->endOfMonth())->endOfDay();

        $candidates = RecruitmentCandidate::query()
            ->without('userInterviewEvaluations.interviewer')
            ->with([
                'vacancy:id,title,department,unit',
                'pic:nik,nama_karyawan',
                'userInterviews' => fn ($query) => $query
                    ->whereBetween('interview_date', [$from->toDateString(), $to->toDateString()])
                    ->orderBy('interview_date')
                    ->orderBy('interview_time'),
            ])
            ->where(function (Builder $query) use ($from, $to): void {
                $query
                    ->whereBetween('interview_hr_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereHas('userInterviews', fn (Builder $interviews) => $interviews
                        ->whereBetween('interview_date', [$from->toDateString(), $to->toDateString()]));
            })
            ->get();

        $employeeNames = $this->employeeNames($candidates);
        $items = $this->agendaItems($candidates, $employeeNames)
            ->filter(fn (array $item): bool => Carbon::parse($item['scheduled_at'])->between($from, $to))
            ->when($filters['type'] ?? null, fn (Collection $rows, string $type) => $rows->where('kind', $type))
            ->when($filters['status'] ?? null, fn (Collection $rows, string $status) => $rows->where('status', $status))
            ->when($filters['search'] ?? null, function (Collection $rows, string $search): Collection {
                $keyword = mb_strtolower(trim($search));

                return $rows->filter(function (array $item) use ($keyword): bool {
                    $haystack = implode(' ', array_filter([
                        $item['candidate_name'], $item['vacancy'], $item['department'], $item['unit'],
                        $item['pic'], $item['label'], $item['location'], implode(' ', $item['interviewers']),
                    ]));

                    return str_contains(mb_strtolower($haystack), $keyword);
                });
            })
            ->sortBy('scheduled_at')
            ->values();

        return response()->json([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total' => $items->count(),
                'upcoming' => $items->where('status', 'upcoming')->count(),
                'overdue' => $items->where('status', 'overdue')->count(),
                'completed' => $items->where('status', 'completed')->count(),
                'hr' => $items->where('kind', 'hr')->count(),
                'user' => $items->where('kind', 'user')->count(),
            ],
            'items' => $items->all(),
        ]);
    }

    private function employeeNames(Collection $candidates): Collection
    {
        $niks = $candidates->flatMap(function (RecruitmentCandidate $candidate): array {
            return [
                $candidate->pic_nik,
                ...$candidate->userInterviews
                    ->flatMap(fn ($interview) => array_map('trim', explode(',', (string) $interview->interviewer_nik)))
                    ->all(),
            ];
        })->filter()->unique()->values();

        return Karyawan::query()->whereIn('nik', $niks)->pluck('nama_karyawan', 'nik');
    }

    private function agendaItems(Collection $candidates, Collection $employeeNames): Collection
    {
        return $candidates->flatMap(function (RecruitmentCandidate $candidate) use ($employeeNames): array {
            $items = [];

            if ($candidate->interview_hr_date && $candidate->interview_hr_time) {
                $at = Carbon::parse($candidate->interview_hr_date.' '.$candidate->interview_hr_time);
                $items[] = $this->item(
                    candidate: $candidate,
                    key: 'hr-'.$candidate->id,
                    kind: 'hr',
                    label: 'Wawancara HR',
                    round: null,
                    at: $at,
                    mode: $candidate->interview_hr_type,
                    location: $candidate->interview_hr_location,
                    meetLink: $candidate->interview_hr_meet_link,
                    completedAt: $candidate->interview_hr_completed_at,
                    emailSentAt: $candidate->interview_hr_email_sent_at,
                    waSentAt: $candidate->interview_hr_wa_sent_at,
                    interviewers: array_values(array_filter([$candidate->pic?->nama_karyawan ?: $candidate->pic_nik])),
                );
            }

            foreach ($candidate->userInterviews as $interview) {
                if (! $interview->interview_date || ! $interview->interview_time) {
                    continue;
                }

                $niks = array_values(array_filter(array_map('trim', explode(',', (string) $interview->interviewer_nik))));
                $items[] = $this->item(
                    candidate: $candidate,
                    key: 'user-'.$interview->id,
                    kind: 'user',
                    label: 'Wawancara User Tahap '.$interview->round,
                    round: (int) $interview->round,
                    at: Carbon::parse($interview->interview_date.' '.$interview->interview_time),
                    mode: $interview->interview_type,
                    location: $interview->interview_location,
                    meetLink: $interview->interview_meet_link,
                    completedAt: $interview->completed_at,
                    emailSentAt: $interview->email_sent_at,
                    waSentAt: $interview->wa_sent_at,
                    interviewers: array_map(fn (string $nik) => $employeeNames->get($nik, $nik), $niks),
                );
            }

            return $items;
        });
    }

    private function item(
        RecruitmentCandidate $candidate,
        string $key,
        string $kind,
        string $label,
        ?int $round,
        Carbon $at,
        ?string $mode,
        ?string $location,
        ?string $meetLink,
        mixed $completedAt,
        mixed $emailSentAt,
        mixed $waSentAt,
        array $interviewers,
    ): array {
        $completed = filled($completedAt);

        return [
            'key' => $key,
            'candidate_id' => $candidate->id,
            'candidate_name' => $candidate->name,
            'candidate_email' => $candidate->email,
            'candidate_phone' => $candidate->phone,
            'candidate_stage' => $candidate->status,
            'vacancy' => $candidate->vacancy?->title ?: 'Umum',
            'department' => $candidate->vacancy?->department,
            'unit' => $candidate->vacancy?->unit,
            'pic' => $candidate->pic?->nama_karyawan ?: $candidate->pic_nik,
            'kind' => $kind,
            'label' => $label,
            'round' => $round,
            'scheduled_at' => $at->toIso8601String(),
            'interview_mode' => $mode ?: 'offline',
            'location' => $location,
            'meet_link' => $meetLink,
            'completed_at' => $completedAt ? Carbon::parse($completedAt)->toIso8601String() : null,
            'status' => $completed ? 'completed' : ($at->copy()->addHour()->isPast() ? 'overdue' : 'upcoming'),
            'email_sent_at' => $emailSentAt ? Carbon::parse($emailSentAt)->toIso8601String() : null,
            'wa_sent_at' => $waSentAt ? Carbon::parse($waSentAt)->toIso8601String() : null,
            'interviewers' => $interviewers,
        ];
    }
}
