<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->string('education_level', 50)->nullable()->after('expected_salary');
            $table->string('education_major', 150)->nullable()->after('education_level');
            $table->string('marital_status', 50)->nullable()->after('education_major');
            $table->string('known_person', 150)->nullable()->after('marital_status');
            $table->string('referred_from', 150)->nullable()->after('known_person');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropColumn([
                'education_level',
                'education_major',
                'marital_status',
                'known_person',
                'referred_from',
            ]);
        });
    }
};
