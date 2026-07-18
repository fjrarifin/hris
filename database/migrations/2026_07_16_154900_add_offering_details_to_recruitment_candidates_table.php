<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->string('last_company', 255)->nullable()->after('notes');
            $table->bigInteger('offered_salary')->nullable()->after('expected_salary');
            $table->date('join_date')->nullable()->after('offered_salary');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table) {
            $table->dropColumn(['last_company', 'offered_salary', 'join_date']);
        });
    }
};
