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
        {--cloud_id= : Override cloud ID Fingerspot}
        {--show-response : Tampilkan response API per chunk untuk debug}';

    protected $description = 'Sync log absensi Fingerspot ke database.';

    public function handle(FingerspotAttendanceService $attendanceService): int
    {
        $startDate = $this->option('start_date') ?: null;
        $endDate = $this->option('end_date') ?: null;

        try {
            $clouds = $this->configuredClouds($this->option('cloud_id') ?: null);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'ok' => true,
            'http_status' => 200,
            'clouds' => [],
            'attendance_sync' => [
                'received' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [],
            ],
        ];

        foreach ($clouds as $cloud) {
            $this->line(sprintf('Cloud %s (%s)', $cloud['name'], $cloud['id']));

            try {
                $result = ($startDate || $endDate)
                    ? $this->syncDateRange($attendanceService, $startDate, $endDate, $cloud['id'])
                    : $attendanceService->syncFromFingerspot(null, null, $cloud['id'], 'scheduled');
            } catch (InvalidArgumentException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->mergeCloudResult($summary, $cloud, $result);
        }

        Log::info('Fingerspot attendance scheduled sync finished', $summary);

        $sync = $summary['attendance_sync'];
        $this->info(sprintf(
            'Fingerspot sync selesai untuk %s cloud. received: %s, created: %s, updated: %s, skipped: %s.',
            count($clouds),
            $sync['received'] ?? 0,
            $sync['created'] ?? 0,
            $sync['updated'] ?? 0,
            $sync['skipped'] ?? 0
        ));

        return $summary['ok'] ? self::SUCCESS : self::FAILURE;
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
                'cloud_id' => $cloudId ?: config('fingerspot.default_cloud_id'),
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

            if ($this->option('show-response')) {
                $this->line('  Request: ' . json_encode($result['request_payload'] ?? []));
                $this->line('  Response: ' . json_encode($result['response'] ?? $result['raw_response'] ?? null));
            }
        }

        return $summary;
    }

    private function configuredClouds(?string $overrideCloudId): array
    {
        if ($overrideCloudId) {
            return [[
                'id' => $overrideCloudId,
                'name' => 'Override',
            ]];
        }

        $clouds = config('fingerspot.clouds', []);

        if (! empty($clouds)) {
            return $clouds;
        }

        $defaultCloudId = config('fingerspot.default_cloud_id');

        if (! $defaultCloudId) {
            throw new InvalidArgumentException('Cloud Fingerspot belum dikonfigurasi di .env.');
        }

        return [[
            'id' => $defaultCloudId,
            'name' => 'Default',
        ]];
    }

    private function mergeCloudResult(array &$summary, array $cloud, array $result): void
    {
        $sync = $result['attendance_sync'] ?? [];
        $summary['ok'] = $summary['ok'] && (bool) ($result['ok'] ?? false);

        if (! ($result['ok'] ?? false)) {
            $summary['http_status'] = $result['http_status'] ?? 500;
        }

        $summary['clouds'][] = [
            'id' => $cloud['id'],
            'name' => $cloud['name'],
            'ok' => $result['ok'] ?? false,
            'http_status' => $result['http_status'] ?? null,
            'attendance_sync' => $sync,
        ];

        foreach (['received', 'created', 'updated', 'skipped'] as $key) {
            $summary['attendance_sync'][$key] += (int) ($sync[$key] ?? 0);
        }

        $summary['attendance_sync']['errors'] = array_merge(
            $summary['attendance_sync']['errors'],
            $sync['errors'] ?? []
        );
    }
}
