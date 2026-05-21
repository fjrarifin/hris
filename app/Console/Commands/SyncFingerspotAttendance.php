<?php

namespace App\Console\Commands;

use App\Services\FingerspotAttendanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SyncFingerspotAttendance extends Command
{
    protected $signature = 'fingerspot:sync-attendance
        {--start_date= : Tanggal awal format Y-m-d, maksimal rentang 2 hari}
        {--end_date= : Tanggal akhir format Y-m-d, maksimal rentang 2 hari}
        {--cloud_id= : Override cloud ID Fingerspot}';

    protected $description = 'Sync log absensi Fingerspot ke database.';

    public function handle(FingerspotAttendanceService $attendanceService): int
    {
        try {
            $result = $attendanceService->syncFromFingerspot(
                $this->option('start_date') ?: null,
                $this->option('end_date') ?: null,
                $this->option('cloud_id') ?: null,
                'scheduled'
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        Log::info('Fingerspot attendance scheduled sync finished', $result);

        $sync = $result['attendance_sync'] ?? [];
        $this->info(sprintf(
            'Fingerspot sync selesai. HTTP %s, received: %s, created: %s, updated: %s, skipped: %s.',
            $result['http_status'],
            $sync['received'] ?? 0,
            $sync['created'] ?? 0,
            $sync['updated'] ?? 0,
            $sync['skipped'] ?? 0
        ));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
