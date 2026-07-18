<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->timestamp('interview_hr_completed_at')->nullable()->after('interview_hr_meet_link');
            $table->foreignId('interview_hr_completed_by')->nullable()->after('interview_hr_completed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('recruitment_candidate_user_interviews', function (Blueprint $table): void {
            $table->timestamp('completed_at')->nullable()->after('interview_meet_link');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });

        $stagesAfterHrInterview = ['case_study', 'interview_user', 'reference_check', 'offering', 'pkb', 'hired'];
        DB::table('recruitment_candidates')
            ->whereNotNull('interview_hr_date')
            ->where(function ($query) use ($stagesAfterHrInterview): void {
                $query
                    ->whereNotNull('interview_hr_summary_path')
                    ->orWhereNotNull('interview_hr_text_summary')
                    ->orWhereIn('status', $stagesAfterHrInterview);
            })
            ->update(['interview_hr_completed_at' => DB::raw('COALESCE(updated_at, created_at)')]);

        $stagesAfterUserInterview = ['reference_check', 'offering', 'pkb', 'hired'];
        DB::table('recruitment_candidate_user_interviews')
            ->where(function ($query) use ($stagesAfterUserInterview): void {
                $query
                    ->whereNotNull('recruitment_candidate_user_interviews.summary_path')
                    ->orWhereExists(function ($evaluation): void {
                        $evaluation
                            ->selectRaw('1')
                            ->from('recruitment_user_interview_evaluations as evaluations')
                            ->whereColumn('evaluations.candidate_id', 'recruitment_candidate_user_interviews.candidate_id')
                            ->whereColumn('evaluations.round', 'recruitment_candidate_user_interviews.round')
                            ->whereNotNull('evaluations.submitted_at');
                    })
                    ->orWhereExists(function ($candidate) use ($stagesAfterUserInterview): void {
                        $candidate
                            ->selectRaw('1')
                            ->from('recruitment_candidates as candidates')
                            ->whereColumn('candidates.id', 'recruitment_candidate_user_interviews.candidate_id')
                            ->whereIn('candidates.status', $stagesAfterUserInterview);
                    });
            })
            ->update(['completed_at' => DB::raw('COALESCE(updated_at, created_at)')]);
    }

    public function down(): void
    {
        Schema::table('recruitment_candidate_user_interviews', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
        });

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('interview_hr_completed_by');
            $table->dropColumn('interview_hr_completed_at');
        });
    }
};
