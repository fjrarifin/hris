<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('t_kontrak_karyawan')) {
            return;
        }

        $today = now()->startOfDay();

        $activeNiks = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $today->copy()->addMonthNoOverflow())
            ->whereDate('end_date', '>=', $today)
            ->distinct()
            ->pluck('nik')
            ->all();

        // Nonaktifkan is_active untuk semua user karyawan (yang memiliki record NIK di t_kontrak_karyawan)
        $allContractNiks = DB::table('t_kontrak_karyawan')->distinct()->pluck('nik')->all();

        if ($allContractNiks !== []) {
            DB::table('users')
                ->whereIn('username', $allContractNiks)
                ->update(['is_active' => 0]);

            if ($activeNiks !== []) {
                DB::table('users')
                    ->whereIn('username', $activeNiks)
                    ->update(['is_active' => 1]);
            }
        }
    }

    public function down(): void
    {
    }
};
