<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payroll_components MODIFY type ENUM('earning','deduction','employer_contribution') NULL");
            DB::statement("ALTER TABLE payroll_items MODIFY type ENUM('earning','deduction','employer_contribution') NOT NULL");
        }

        Schema::table('payroll_components', function (Blueprint $table): void {
            if (! Schema::hasColumn('payroll_components', 'input_mode')) {
                $table->string('input_mode', 20)->default('manual')->after('type');
            }
        });

        DB::table('payroll_components')
            ->whereIn('nama', ['JKN Perusahaan', 'JHT Perusahaan', 'JP Perusahaan', 'JKK Perusahaan', 'JKM Perusahaan'])
            ->update(['type' => 'employer_contribution', 'input_mode' => 'calculated']);

        DB::table('payroll_components')
            ->whereIn('nama', [
                'Gaji Pokok',
                'Lembur',
                'Potongan Izin',
                'Potongan Sakit Tanpa Surat',
                'Pot. JKN Karyawan',
                'Pot. JHT Karyawan',
                'Pot. JP Karyawan',
            ])
            ->update(['input_mode' => 'calculated']);
    }

    public function down(): void
    {
        DB::table('payroll_components')
            ->whereIn('nama', ['JKN Perusahaan', 'JHT Perusahaan', 'JP Perusahaan', 'JKK Perusahaan', 'JKM Perusahaan'])
            ->update(['type' => 'earning']);

        Schema::table('payroll_components', function (Blueprint $table): void {
            if (Schema::hasColumn('payroll_components', 'input_mode')) {
                $table->dropColumn('input_mode');
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payroll_items MODIFY type ENUM('earning','deduction') NOT NULL");
            DB::statement("ALTER TABLE payroll_components MODIFY type ENUM('earning','deduction') NULL");
        }
    }
};
