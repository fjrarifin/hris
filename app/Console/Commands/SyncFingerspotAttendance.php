<?php

namespace App\Console\Commands;

use App\Services\FingerspotAttendanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SyncFingerspotAttendance extends Command
{
    protected $signature = 'fingerspot:sync-attendance
        {--start_date= : Tanggal awal format Y-m-d}
        {--end_date= : Tanggal akhir format Y-m-d}
        {--cloud_id= : Override cloud ID Fingerspot}';

    protected $description = 'Sync log absensi Fingerspot ke database.';

    public function handle(FingerspotAttendanceService $attendanceService): int
    {
        $startDate = $this->option('start_date') ?: null;
        $endDate = $this->option('end_date') ?: null;
        $cloudId = $this->option('cloud_id') ?: null;

        try {
            $result = ($startDate || $endDate)
                ? $this->syncDateRange($attendanceService, $startDate, $endDate, $cloudId)
                : $attendanceService->syncFromFingerspot(null, null, $cloudId, 'scheduled');
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

    private function syncDateRange(
        FingerspotAttendanceService $attendanceService,
        ?string $startDate,
        ?string $endDate,
        ?string $cloudId
    ): array {
        $start = Carbon::parse($startDate ?: $endDate)->startOfDay();
        $end = Carbon::parse($endDate ?: $startDate)->startOfDay();

        if ($start->gt($end)) {
            throw new InvalidArgumentException('Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        $summary = [
            'ok' => true,
            'http_status' => 200,
            'request_payload' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'cloud_id' => $cloudId ?: env('FINGERSPOT_CLOUD_ID'),
            ],
            'chunks' => [],
            'attendance_sync' => [
                'received' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ],
        ];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDays(2)) {
            $chunkStart = $cursor->copy();
            $chunkEnd = $cursor->copy()->addDay()->min($end);

            $this->line(sprintf(
                'Sync Fingerspot %s s/d %s...',
                $chunkStart->toDateString(),
                $chunkEnd->toDateString()
            ));

            $result = $attendanceService->syncFromFingerspot($chunkStart, $chunkEnd, $cloudId, 'manual');
            $sync = $result['attendance_sync'] ?? [];

            $summary['ok'] = $summary['ok'] && (bool) $result['ok'];
            $summary['http_status'] = $result['ok'] ? $summary['http_status'] : $result['http_status'];
            $summary['chunks'][] = [
                'start_date' => $chunkStart->toDateString(),
                'end_date' => $chunkEnd->toDateString(),
                'http_status' => $result['http_status'],
                'attendance_sync' => $sync,
            ];

            foreach (['received', 'created', 'updated', 'skipped'] as $key) {
                $summary['attendance_sync'][$key] += (int) ($sync[$key] ?? 0);
            }

            $summary['attendance_sync']['errors'] = array_merge(
                $summary['attendance_sync']['errors'],
                $sync['errors'] ?? []
            );

            $this->line(sprintf(
                '  HTTP %s, received: %s, created: %s, updated: %s, skipped: %s.',
                $result['http_status'],
                $sync['received'] ?? 0,
                $sync['created'] ?? 0,
                $sync['updated'] ?? 0,
                $sync['skipped'] ?? 0
            ));
        }

        return $summary;
    }
}
