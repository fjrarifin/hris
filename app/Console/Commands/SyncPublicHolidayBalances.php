<?php

namespace App\Console\Commands;

use App\Services\PublicHolidayBalanceService;
use Illuminate\Console\Command;

class SyncPublicHolidayBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hris:sync-ph-balances {--fresh : Kosongkan tabel employee_ph_balances terlebih dahulu sebelum sinkronisasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi record saldo PH ke employee_ph_balances dari presensi karyawan untuk hari libur dalam rentang 90 hari';

    /**
     * Execute the console command.
     */
    public function handle(PublicHolidayBalanceService $balanceService): int
    {
        $isFresh = (bool) $this->option('fresh');

        if ($isFresh) {
            $this->warn('Opsi --fresh aktif: Tabel employee_ph_balances dikosongkan terlebih dahulu.');
        }

        $this->info('Memulai sinkronisasi saldo Public Holiday dari absensi (maksimal 90 hari terakhir)...');

        $result = $balanceService->rebuildBalancesFromAttendance($isFresh);

        if (empty($result['breakdown'])) {
            $this->info('Tidak ada hari libur nasional aktif dalam rentang 90 hari terakhir.');
            return self::SUCCESS;
        }

        $headers = ['ID', 'Nama Hari Libur', 'Tanggal', 'Usia (Hari)', 'Status <= 90 Hari', 'Record Saldo Diberikan'];
        $rows = array_map(function ($item) {
            return [
                $item['id'],
                $item['name'],
                $item['date'],
                $item['days_ago'] . ' hari lalu',
                'Ya (Eligible)',
                $item['inserted'] . ' karyawan',
            ];
        }, $result['breakdown']);

        $this->table($headers, $rows);

        $this->info("Sinkronisasi selesai! Total {$result['records_inserted']} record saldo PH berhasil disimpan ke employee_ph_balances.");

        return self::SUCCESS;
    }
}
