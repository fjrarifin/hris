<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->string('unit', 100)->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }
};
