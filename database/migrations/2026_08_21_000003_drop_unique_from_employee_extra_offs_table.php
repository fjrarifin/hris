<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_extra_offs', function (Blueprint $table): void {
            $table->dropUnique('extra_off_employee_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('employee_extra_offs', function (Blueprint $table): void {
            $table->unique(['karyawan_nik', 'periode_start', 'periode_end'], 'extra_off_employee_period_unique');
        });
    }
};
