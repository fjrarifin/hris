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
            $table->json('onboarding_data')->nullable()->after('onboarding_completed_at');
            $table->string('employee_nik', 50)->nullable()->after('onboarding_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn(['onboarding_data', 'employee_nik']);
        });
    }
};
