<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUserActiveStatus extends Command
{
    protected $signature = 'users:sync-active {--date= : Tanggal acuan, default hari ini (Y-m-d)}';

    protected $description = 'Sinkronkan is_active pada tabel users (ubah is_active = 0 jika karyawan tidak memiliki kontrak aktif).';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $activeNiks = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $today->copy()->addMonthNoOverflow())
            ->whereDate('end_date', '>=', $today)
            ->distinct()
            ->pluck('nik')
            ->all();

        $allContractNiks = DB::table('t_kontrak_karyawan')
            ->distinct()
            ->pluck('nik')
            ->all();

        if (empty($allContractNiks)) {
            $this->info('Tidak ada data kontrak karyawan yang ditemukan di t_kontrak_karyawan.');

            return self::SUCCESS;
        }

        // Nonaktifkan user yang tidak memiliki kontrak aktif lagi
        $deactivatedCount = DB::table('users')
            ->whereIn('username', $allContractNiks)
            ->whereNotIn('username', $activeNiks)
            ->update(['is_active' => 0]);

        // Pastikan user yang memiliki kontrak aktif tetap is_active = 1
        $activatedCount = DB::table('users')
            ->whereIn('username', $activeNiks)
            ->update(['is_active' => 1]);

        $this->info('Sinkronisasi akun user selesai!');
        $this->info("- User aktif (memiliki kontrak aktif): {$activatedCount}");
        $this->info("- User dinonaktifkan (tanpa kontrak aktif): {$deactivatedCount}");

        return self::SUCCESS;
    }
}
