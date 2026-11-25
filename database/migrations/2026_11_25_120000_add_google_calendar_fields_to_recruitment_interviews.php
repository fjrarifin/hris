<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('recruitment_candidates')) {
            Schema::table('recruitment_candidates', function (Blueprint $table): void {
                if (! Schema::hasColumn('recruitment_candidates', 'interview_hr_gcal_event_id')) {
                    $table->string('interview_hr_gcal_event_id', 255)->nullable()->after('interview_hr_meet_link');
                }
                if (! Schema::hasColumn('recruitment_candidates', 'interview_hr_gcal_event_link')) {
                    $table->string('interview_hr_gcal_event_link', 500)->nullable()->after('interview_hr_gcal_event_id');
                }
                if (! Schema::hasColumn('recruitment_candidates', 'interview_hr_gcal_synced_at')) {
                    $table->timestamp('interview_hr_gcal_synced_at')->nullable()->after('interview_hr_gcal_event_link');
                }
            });
        }

        if (Schema::hasTable('recruitment_candidate_user_interviews')) {
            Schema::table('recruitment_candidate_user_interviews', function (Blueprint $table): void {
                if (! Schema::hasColumn('recruitment_candidate_user_interviews', 'gcal_event_id')) {
                    $table->string('gcal_event_id', 255)->nullable()->after('interview_meet_link');
                }
                if (! Schema::hasColumn('recruitment_candidate_user_interviews', 'gcal_event_link')) {
                    $table->string('gcal_event_link', 500)->nullable()->after('gcal_event_id');
                }
                if (! Schema::hasColumn('recruitment_candidate_user_interviews', 'gcal_synced_at')) {
                    $table->timestamp('gcal_synced_at')->nullable()->after('gcal_event_link');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('recruitment_candidates')) {
            Schema::table('recruitment_candidates', function (Blueprint $table): void {
                $columns = array_values(array_filter([
                    Schema::hasColumn('recruitment_candidates', 'interview_hr_gcal_event_id') ? 'interview_hr_gcal_event_id' : null,
                    Schema::hasColumn('recruitment_candidates', 'interview_hr_gcal_event_link') ? 'interview_hr_gcal_event_link' : null,
                    Schema::hasColumn('recruitment_candidates', 'interview_hr_gcal_synced_at') ? 'interview_hr_gcal_synced_at' : null,
                ]));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('recruitment_candidate_user_interviews')) {
            Schema::table('recruitment_candidate_user_interviews', function (Blueprint $table): void {
                $columns = array_values(array_filter([
                    Schema::hasColumn('recruitment_candidate_user_interviews', 'gcal_event_id') ? 'gcal_event_id' : null,
                    Schema::hasColumn('recruitment_candidate_user_interviews', 'gcal_event_link') ? 'gcal_event_link' : null,
                    Schema::hasColumn('recruitment_candidate_user_interviews', 'gcal_synced_at') ? 'gcal_synced_at' : null,
                ]));
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
