<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('employee_payroll_profiles', 'bruto_man_power')) {
                $table->unsignedBigInteger('bruto_man_power')->default(0)->after('tunjangan_tidak_tetap');
            }

            if (! Schema::hasColumn('employee_payroll_profiles', 'payroll_group')) {
                $table->string('payroll_group', 30)->default('staff')->after('bruto_man_power');
            }
        });

        Schema::table('payrolls', function (Blueprint $table): void {
            if (! Schema::hasColumn('payrolls', 'basic_salary')) {
                $table->unsignedBigInteger('basic_salary')->default(0)->after('ph');
            }

            if (! Schema::hasColumn('payrolls', 'bruto_man_power')) {
                $table->unsignedBigInteger('bruto_man_power')->default(0)->after('basic_salary');
            }

            if (! Schema::hasColumn('payrolls', 'total_hari_masuk')) {
                $table->unsignedInteger('total_hari_masuk')->default(0)->after('bruto_man_power');
            }

            if (! Schema::hasColumn('payrolls', 'extra_off_days')) {
                $table->unsignedInteger('extra_off_days')->default(0)->after('total_hari_masuk');
            }

            if (! Schema::hasColumn('payrolls', 'tunjangan_tidak_tetap_full')) {
                $table->unsignedBigInteger('tunjangan_tidak_tetap_full')->default(0)->after('extra_off_days');
            }

            if (! Schema::hasColumn('payrolls', 'formula_version')) {
                $table->string('formula_version', 50)->nullable()->after('tunjangan_tidak_tetap_full');
            }
        });

        DB::table('employee_payroll_profiles')
            ->where('bruto_man_power', 0)
            ->where('tunjangan_tidak_tetap', '>', 0)
            ->update([
                'bruto_man_power' => DB::raw('ROUND((tunjangan_tidak_tetap + ((gaji_pokok + tunjangan_jabatan) * 0.1424)) / 0.25)'),
            ]);

        if (Schema::hasTable('m_karyawan')) {
            $operatorNiks = DB::table('m_karyawan')
                ->where(function ($query): void {
                    $query->where('posisi', 'like', '%operator%')
                        ->orWhere('jabatan', 'like', '%operator%')
                        ->orWhere('posisi_title', 'like', '%operator%');
                })
                ->pluck('nik');

            DB::table('employee_payroll_profiles')
                ->whereIn('karyawan_nik', $operatorNiks)
                ->update(['payroll_group' => 'operator']);
        }
    }

    public function down(): void
    {
        // Non-destructive payroll migration: keep added columns for production safety.
    }
};
