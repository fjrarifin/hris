<?php

namespace App\Console\Commands;

use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireEmployeeContracts extends Command
{
    protected $signature = 'contracts:expire {--date= : Tanggal acuan, default hari ini (Y-m-d)}';

    protected $description = 'Nonaktifkan kontrak aktif yang tanggal akhirnya sudah lewat dan sinkronkan status karyawan.';

    public function handle(): int
    {
        $today = $this->option('date')
            ? Carbon::parse((string) $this->option('date'))->startOfDay()
            : now()->startOfDay();

        $expiredContracts = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('end_date', '<', $today)
            ->pluck('nik')
            ->unique()
            ->values();

        $updatedContracts = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('end_date', '<', $today)
            ->update([
                'status_kontrak' => 'NONAKTIF',
                'updated_at' => now(),
            ]);

        foreach ($expiredContracts as $nik) {
            $hasActiveContract = DB::table('t_kontrak_karyawan')
                ->where('nik', $nik)
                ->where('status_kontrak', 'AKTIF')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->exists();

            Karyawan::query()->where('nik', $nik)->update([
                'status_karyawan' => $hasActiveContract ? 'AKTIF' : 'NONAKTIF',
            ]);
        }

        $this->info("Kontrak kedaluwarsa dinonaktifkan: {$updatedContracts}. Karyawan disinkronkan: {$expiredContracts->count()}.");

        return self::SUCCESS;
    }
}
