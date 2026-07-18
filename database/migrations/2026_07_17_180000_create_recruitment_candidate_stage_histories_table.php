<?php

use App\Models\RecruitmentCandidate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STAGE_ALIASES = [
        'interview' => 'interview_hr',
        'offered' => 'offering',
    ];

    public function up(): void
    {
        Schema::create('recruitment_candidate_stage_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->cascadeOnDelete();
            $table->string('stage', 50);
            $table->timestamp('entered_at');
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['candidate_id', 'exited_at'], 'recruitment_stage_history_open_idx');
            $table->index(['stage', 'entered_at'], 'recruitment_stage_history_stage_idx');
        });

        $this->backfillExistingCandidates();
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidate_stage_histories');
    }

    private function backfillExistingCandidates(): void
    {
        DB::table('recruitment_candidates')
            ->orderBy('id')
            ->each(function (object $candidate): void {
                $transitions = DB::table('hrd_audit_logs')
                    ->where('subject_type', RecruitmentCandidate::class)
                    ->where('subject_id', (string) $candidate->id)
                    ->orderByRaw('COALESCE(occurred_at, created_at)')
                    ->get()
                    ->map(function (object $log): ?array {
                        $changes = json_decode((string) $log->changes, true) ?: [];
                        $statusChange = collect($changes)->firstWhere('field', 'status');

                        if (! $statusChange || empty($statusChange['new'])) {
                            return null;
                        }

                        return [
                            'from' => $this->normalizeStage($statusChange['old'] ?? null),
                            'to' => $this->normalizeStage($statusChange['new']),
                            'at' => Carbon::parse($log->occurred_at ?: $log->created_at),
                            'actor_user_id' => $log->actor_user_id,
                            'audit_log_id' => $log->id,
                        ];
                    })
                    ->filter()
                    ->values();

                $enteredAt = Carbon::parse($candidate->created_at);
                $currentStage = $transitions->first()['from'] ?? 'applied';
                $currentStage = $currentStage ?: 'applied';

                foreach ($transitions as $transition) {
                    if ($transition['to'] === $currentStage) {
                        continue;
                    }

                    $this->insertHistory(
                        (int) $candidate->id,
                        $currentStage,
                        $enteredAt,
                        $transition['at'],
                        $transition['actor_user_id'],
                        ['backfilled' => true, 'source' => 'hrd_audit_logs', 'audit_log_id' => $transition['audit_log_id']],
                    );

                    $currentStage = $transition['to'];
                    $enteredAt = $transition['at'];
                }

                $actualStage = $this->normalizeStage($candidate->status) ?: 'applied';
                if ($currentStage !== $actualStage) {
                    $fallbackAt = Carbon::parse($candidate->updated_at ?: $candidate->created_at);
                    if ($fallbackAt->lt($enteredAt)) {
                        $fallbackAt = $enteredAt->copy();
                    }

                    $this->insertHistory(
                        (int) $candidate->id,
                        $currentStage,
                        $enteredAt,
                        $fallbackAt,
                        null,
                        ['backfilled' => true, 'source' => 'candidate_status_fallback'],
                    );
                    $currentStage = $actualStage;
                    $enteredAt = $fallbackAt;
                }

                $this->insertHistory(
                    (int) $candidate->id,
                    $currentStage,
                    $enteredAt,
                    null,
                    null,
                    ['backfilled' => true, 'source' => $transitions->isNotEmpty() ? 'hrd_audit_logs' : 'candidate_current_status'],
                );
            });
    }

    private function insertHistory(
        int $candidateId,
        string $stage,
        Carbon $enteredAt,
        ?Carbon $exitedAt,
        ?int $actorUserId,
        array $metadata,
    ): void {
        DB::table('recruitment_candidate_stage_histories')->insert([
            'candidate_id' => $candidateId,
            'stage' => $stage,
            'entered_at' => $enteredAt,
            'exited_at' => $exitedAt,
            'actor_user_id' => $actorUserId,
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function normalizeStage(?string $stage): ?string
    {
        return $stage ? (self::STAGE_ALIASES[$stage] ?? $stage) : null;
    }
};
