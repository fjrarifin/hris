<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            if (! Schema::hasColumn('recruitment_vacancies', 'custom_questions')) {
                $table->json('custom_questions')->nullable()->after('benefits');
            }
        });

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            if (! Schema::hasColumn('recruitment_candidates', 'years_of_experience')) {
                $table->string('years_of_experience', 50)->nullable()->after('expected_salary');
            }
            if (! Schema::hasColumn('recruitment_candidates', 'willing_to_work_in_bandung')) {
                $table->string('willing_to_work_in_bandung', 10)->nullable()->after('years_of_experience');
            }
            if (! Schema::hasColumn('recruitment_candidates', 'custom_answers')) {
                $table->json('custom_answers')->nullable()->after('willing_to_work_in_bandung');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            if (Schema::hasColumn('recruitment_vacancies', 'custom_questions')) {
                $table->dropColumn('custom_questions');
            }
        });

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropColumn(['years_of_experience', 'willing_to_work_in_bandung', 'custom_answers']);
        });
    }
};
