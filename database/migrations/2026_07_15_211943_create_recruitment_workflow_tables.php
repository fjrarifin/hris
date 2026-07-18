<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->string('status', 50)->default('applied')->change();

            // HR Interview
            $table->date('interview_hr_date')->nullable();
            $table->time('interview_hr_time')->nullable();
            $table->string('interview_hr_type', 20)->nullable();
            $table->string('interview_hr_location', 255)->nullable();
            $table->string('interview_hr_meet_link', 255)->nullable();
            $table->string('interview_hr_summary_path', 255)->nullable();

            // Case Study
            $table->string('case_study_document_path', 255)->nullable();
            $table->string('case_study_link', 255)->nullable();
            $table->timestamp('case_study_sent_at')->nullable();
            $table->string('case_study_submitted_file_path', 255)->nullable();

            // Reference Check
            $table->string('reference_check_token', 100)->nullable()->index();
            $table->string('reference_check_summary_path', 255)->nullable();

            // Offering Letter Extra
            $table->unsignedInteger('previous_salary')->nullable();
            $table->string('offering_letter_signed_path', 255)->nullable();
            $table->longText('offering_letter_signature_data')->nullable();
            $table->timestamp('offering_letter_signed_at')->nullable();

            // Onboarding
            $table->string('onboarding_token', 100)->nullable()->index();
            $table->string('onboarding_password', 20)->nullable();
            $table->timestamp('onboarding_sent_at')->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
        });

        // User Interviews
        Schema::create('recruitment_candidate_user_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->onDelete('cascade');
            $table->unsignedTinyInteger('round');
            $table->date('interview_date')->nullable();
            $table->time('interview_time')->nullable();
            $table->string('interviewer_nik', 50)->nullable();
            $table->string('interview_type', 20)->nullable();
            $table->string('interview_location', 255)->nullable();
            $table->string('interview_meet_link', 255)->nullable();
            $table->string('summary_path', 255)->nullable();
            
            // Score evaluation fields
            $table->tinyInteger('interview_appearance')->nullable();
            $table->tinyInteger('interview_attitude')->nullable();
            $table->tinyInteger('interview_communication')->nullable();
            $table->tinyInteger('interview_motivation')->nullable();
            $table->tinyInteger('interview_initiative')->nullable();
            $table->tinyInteger('interview_teamwork')->nullable();
            $table->tinyInteger('interview_domain_experience')->nullable();
            $table->tinyInteger('interview_general_knowledge')->nullable();
            $table->tinyInteger('interview_growth_potential')->nullable();
            $table->integer('interview_total_score')->nullable();
            $table->text('interview_evaluation_notes')->nullable();
            $table->string('interview_recommendation', 50)->nullable();
            $table->timestamps();
        });

        // References
        Schema::create('recruitment_candidate_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->onDelete('cascade');
            $table->string('name', 150);
            $table->string('phone', 50);
            $table->string('company', 150);
            $table->string('position', 150);
            $table->timestamps();
        });

        // PKB Signers
        Schema::create('recruitment_candidate_pkb_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->onDelete('cascade');
            $table->string('employee_nik', 50)->index();
            $table->timestamp('signed_at')->nullable();
            $table->longText('signature_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidate_pkb_signers');
        Schema::dropIfExists('recruitment_candidate_references');
        Schema::dropIfExists('recruitment_candidate_user_interviews');

        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'interview_hr_date', 'interview_hr_time', 'interview_hr_type', 'interview_hr_location', 'interview_hr_meet_link', 'interview_hr_summary_path',
                'case_study_document_path', 'case_study_link', 'case_study_sent_at', 'case_study_submitted_file_path',
                'reference_check_token', 'reference_check_summary_path',
                'previous_salary', 'offering_letter_signed_path', 'offering_letter_signature_data', 'offering_letter_signed_at',
                'onboarding_token', 'onboarding_password', 'onboarding_sent_at', 'onboarding_completed_at'
            ]);
        });
    }
};
