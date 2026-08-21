<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_ph_adjustments') && ! Schema::hasColumn('employee_ph_adjustments', 'public_holiday_id')) {
            Schema::table('employee_ph_adjustments', function (Blueprint $table): void {
                $table->foreignId('public_holiday_id')->nullable()->after('karyawan_nik')->constrained('public_holidays')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('employee_ph_adjustments') && Schema::hasColumn('employee_ph_adjustments', 'public_holiday_id')) {
            Schema::table('employee_ph_adjustments', function (Blueprint $table): void {
                $table->dropForeign(['public_holiday_id']);
                $table->dropColumn('public_holiday_id');
            });
        }
    }
};
