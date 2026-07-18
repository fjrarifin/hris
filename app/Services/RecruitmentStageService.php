<?php

namespace App\Services;

use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateStageHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecruitmentStageService
{
    public const STAGES = [
        'applied',
        'screening',
        'interview_hr',
        'case_study',
        'interview_user',
        'reference_check',
        'offering',
        'pkb',
        'hired',
        'rejected',
    ];

    private const ALIASES = [
        'interview' => 'interview_hr',
        'offered' => 'offering',
    ];

    public function recordInitial(RecruitmentCandidate $candidate, ?User $actor = null): RecruitmentCandidateStageHistory
    {
        return RecruitmentCandidateStageHistory::query()->firstOrCreate(
            [
                'candidate_id' => $candidate->id,
                'exited_at' => null,
            ],
            [
                'stage' => $this->normalize($candidate->status),
                'entered_at' => $candidate->created_at ?: now(),
                'actor_user_id' => $actor?->id,
                'metadata' => ['source' => 'candidate_created'],
            ],
        );
    }

    public function transition(
        RecruitmentCandidate $candidate,
        string $toStage,
        ?User $actor = null,
        ?string $reason = null,
        array $metadata = [],
    ): RecruitmentCandidate {
        $toStage = $this->normalize($toStage);
        if (! in_array($toStage, self::STAGES, true)) {
            throw ValidationException::withMessages(['status' => 'Tahap recruitment tidak valid.']);
        }

        return DB::transaction(function () use ($candidate, $toStage, $actor, $reason, $metadata): RecruitmentCandidate {
            $lockedCandidate = RecruitmentCandidate::query()->lockForUpdate()->findOrFail($candidate->id);
            $fromStage = $this->normalize($lockedCandidate->status);

            $openHistory = RecruitmentCandidateStageHistory::query()
                ->where('candidate_id', $lockedCandidate->id)
                ->whereNull('exited_at')
                ->latest('entered_at')
                ->lockForUpdate()
                ->first();

            if (! $openHistory) {
                $openHistory = RecruitmentCandidateStageHistory::query()->create([
                    'candidate_id' => $lockedCandidate->id,
                    'stage' => $fromStage,
                    'entered_at' => $lockedCandidate->created_at ?: now(),
                    'metadata' => ['source' => 'transition_recovery'],
                ]);
            }

            if ($fromStage === $toStage) {
                return $lockedCandidate;
            }

            $changedAt = now();
            $openHistory->update(['exited_at' => $changedAt]);

            RecruitmentCandidateStageHistory::query()->create([
                'candidate_id' => $lockedCandidate->id,
                'stage' => $toStage,
                'entered_at' => $changedAt,
                'actor_user_id' => $actor?->id,
                'reason' => $reason,
                'metadata' => [
                    ...$metadata,
                    'from_stage' => $fromStage,
                    'source' => $metadata['source'] ?? 'workflow',
                ],
            ]);

            $lockedCandidate->forceFill(['status' => $toStage])->save();

            return $lockedCandidate->refresh();
        });
    }

    public function normalize(?string $stage): string
    {
        $stage = $stage ?: 'applied';

        return self::ALIASES[$stage] ?? $stage;
    }
}
