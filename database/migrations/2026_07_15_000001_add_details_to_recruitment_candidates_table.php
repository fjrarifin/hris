<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->unsignedInteger('expected_salary')->nullable();
            $table->string('photo_path', 255)->nullable();
            
            // Interview Details
            $table->date('interview_date')->nullable();
            $table->time('interview_time')->nullable();
            $table->string('interviewer_nik', 50)->nullable()->index();
            $table->enum('interview_type', ['online', 'offline'])->nullable();
            $table->string('interview_location', 255)->nullable();
            $table->string('interview_meet_link', 255)->nullable();
            $table->boolean('interview_is_locked')->default(false);
            
            // Offering Letter
            $table->string('offering_letter_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropIndex(['interviewer_nik']);
            
            $table->dropColumn([
                'expected_salary',
                'photo_path',
                'interview_date',
                'interview_time',
                'interviewer_nik',
                'interview_type',
                'interview_location',
                'interview_meet_link',
                'interview_is_locked',
                'offering_letter_path',
            ]);
        });
    }
};
