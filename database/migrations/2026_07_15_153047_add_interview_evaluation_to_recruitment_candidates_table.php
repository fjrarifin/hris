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
            $table->string('interview_recommendation')->nullable(); // 'tidak_disarankan', 'dipertimbangkan', 'disarankan'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn([
                'interview_appearance',
                'interview_attitude',
                'interview_communication',
                'interview_motivation',
                'interview_initiative',
                'interview_teamwork',
                'interview_domain_experience',
                'interview_general_knowledge',
                'interview_growth_potential',
                'interview_total_score',
                'interview_evaluation_notes',
                'interview_recommendation',
            ]);
        });
    }
};
