<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->string('division', 100)->nullable()->after('title');
            $table->string('position', 100)->nullable()->after('unit');
            $table->string('supervisor_nik', 30)->nullable()->after('position');
            $table->string('supervisor_name', 150)->nullable()->after('supervisor_nik');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_vacancies', function (Blueprint $table): void {
            $table->dropColumn(['division', 'position', 'supervisor_nik', 'supervisor_name']);
        });
    }
};
