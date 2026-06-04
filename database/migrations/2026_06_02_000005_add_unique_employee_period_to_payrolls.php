<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'payrolls_employee_period_unique';

    public function up(): void
    {
        $hasDuplicates = DB::table('payrolls')
            ->select('karyawan_nik', 'periode_start', 'periode_end')
            ->groupBy('karyawan_nik', 'periode_start', 'periode_end')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new \RuntimeException('Unique index payroll gagal dibuat: terdapat payroll duplikat untuk NIK dan periode yang sama.');
        }

        Schema::table('payrolls', function (Blueprint $table): void {
            $table->unique(['karyawan_nik', 'periode_start', 'periode_end'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
