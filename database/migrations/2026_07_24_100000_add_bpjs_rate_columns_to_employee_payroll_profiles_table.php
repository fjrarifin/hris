<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jkn_karyawan_percent')) {
                $table->decimal('rate_jkn_karyawan_percent', 5, 2)->default(1.00)->after('rate_jkk_percent');
            }
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jkn_perusahaan_percent')) {
                $table->decimal('rate_jkn_perusahaan_percent', 5, 2)->default(4.00)->after('rate_jkn_karyawan_percent');
            }
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jht_karyawan_percent')) {
                $table->decimal('rate_jht_karyawan_percent', 5, 2)->default(2.00)->after('rate_jkn_perusahaan_percent');
            }
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jht_perusahaan_percent')) {
                $table->decimal('rate_jht_perusahaan_percent', 5, 2)->default(3.70)->after('rate_jht_karyawan_percent');
            }
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jp_karyawan_percent')) {
                $table->decimal('rate_jp_karyawan_percent', 5, 2)->default(1.00)->after('rate_jht_perusahaan_percent');
            }
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jp_perusahaan_percent')) {
                $table->decimal('rate_jp_perusahaan_percent', 5, 2)->default(2.00)->after('rate_jp_karyawan_percent');
            }
            if (! Schema::hasColumn('employee_payroll_profiles', 'rate_jkm_percent')) {
                $table->decimal('rate_jkm_percent', 5, 2)->default(0.30)->after('rate_jp_perusahaan_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'rate_jkn_karyawan_percent',
                'rate_jkn_perusahaan_percent',
                'rate_jht_karyawan_percent',
                'rate_jht_perusahaan_percent',
                'rate_jp_karyawan_percent',
                'rate_jp_perusahaan_percent',
                'rate_jkm_percent',
            ]);
        });
    }
};
