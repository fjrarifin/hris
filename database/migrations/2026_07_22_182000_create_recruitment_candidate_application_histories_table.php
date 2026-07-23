<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_candidate_application_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->cascadeOnDelete();
            $table->foreignId('vacancy_id')->nullable()->constrained('recruitment_vacancies')->nullOnDelete();
            $table->string('vacancy_title', 255)->nullable();
            $table->string('status', 50);
            $table->timestamp('applied_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('interview_hr_text_summary')->nullable();
            $table->string('interview_hr_summary_path', 255)->nullable();
            $table->string('case_study_submitted_file_path', 255)->nullable();
            $table->timestamp('case_study_submitted_at')->nullable();
            $table->unsignedBigInteger('offered_salary')->nullable();
            $table->date('join_date')->nullable();
            $table->string('offering_letter_path', 255)->nullable();
            $table->timestamp('offering_letter_signed_at')->nullable();
            $table->json('snapshot_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidate_application_histories');
    }
};
