<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_user_interview_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained('recruitment_candidates')->onDelete('cascade');
            $table->unsignedTinyInteger('round');
            $table->string('interviewer_nik', 50)->index();
            $table->string('token', 100)->unique();
            
            // Aspek Penilaian
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
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_user_interview_evaluations');
    }
};
