<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('m_karyawan')
            || ! Schema::hasTable('t_kontrak_karyawan')
            || ! Schema::hasColumn('m_karyawan', 'status_karyawan')) {
            return;
        }

        $activeNiks = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->distinct()
            ->pluck('nik')
            ->all();

        DB::table('m_karyawan')->update(['status_karyawan' => 'NONAKTIF']);

        if ($activeNiks !== []) {
            DB::table('m_karyawan')
                ->whereIn('nik', $activeNiks)
                ->update(['status_karyawan' => 'AKTIF']);
        }
    }

    public function down(): void
    {
        // Status lama bersifat data historis dan tidak dapat dipulihkan secara akurat.
    }
};
