<?php

namespace App\Services;

use App\Models\FingerspotAttendanceLog;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class FingerspotAttendanceService
{
    public function syncFromFingerspot(
        string|Carbon|null $startDate = null,
        string|Carbon|null $endDate = null,
        ?string $cloudId = null,
        string $source = 'pull'
    ): array {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->subDay()->startOfDay();
        $end = $endDate ? Carbon::parse($endDate)->startOfDay() : now()->startOfDay();

        if ($start->gt($end)) {
            throw new InvalidArgumentException('Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
        }

        if ($start->diffInDays($end) > 1) {
            throw new InvalidArgumentException('Fingerspot hanya bisa ditarik maksimal 2 hari per request.');
        }

        $cloudId ??= env('FINGERSPOT_CLOUD_ID');

        if (! $cloudId) {
            throw new InvalidArgumentException('FINGERSPOT_CLOUD_ID belum diset di .env.');
        }

        $payload = [
            'trans_id' => 'ATTLOG-' . now()->format('YmdHis'),
            'cloud_id' => $cloudId,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('FINGERSPOT_API_TOKEN'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout(30)
            ->post(rtrim(env('FINGERSPOT_BASE_URL', 'https://developer.fingerspot.io/api'), '/') . '/get_attlog', $payload);

        $responsePayload = $response->json();
        $syncResult = is_array($responsePayload)
            ? $this->storeFromApiResponse($responsePayload, $payload, $source)
            : null;

        return [
            'ok' => $response->successful(),
            'http_status' => $response->status(),
            'request_payload' => $payload,
            'response' => $responsePayload,
            'raw_response' => $response->body(),
            'attendance_sync' => $syncResult,
        ];
    }

    public function storeFromApiResponse(array $payload, array $requestPayload = [], string $source = 'pull'): array
    {
        return $this->storeRecords($this->extractRecords($payload), [
            'trans_id' => Arr::get($payload, 'trans_id', Arr::get($requestPayload, 'trans_id')),
            'cloud_id' => Arr::get($payload, 'cloud_id', Arr::get($requestPayload, 'cloud_id')),
            'source' => $source,
        ]);
    }

    public function storeFromWebhook(array $payload): array
    {
        return $this->storeRecords($this->extractRecords($payload), [
            'trans_id' => Arr::get($payload, 'trans_id'),
            'cloud_id' => Arr::get($payload, 'cloud_id'),
            'source' => 'webhook',
        ]);
    }

    private function storeRecords(array $records, array $context): array
    {
        $result = [
            'received' => count($records),
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($records as $record) {
            $normalized = $this->normalizeRecord($record);

            if ($normalized === null) {
                $result['skipped']++;
                continue;
            }

            try {
                $log = FingerspotAttendanceLog::updateOrCreate(
                    [
                        'pin' => $normalized['pin'],
                        'scan_date' => $normalized['scan_date'],
                        'status_scan' => $normalized['status_scan'],
                    ],
                    [
                        'verify' => $normalized['verify'],
                        'trans_id' => $context['trans_id'] ?? null,
                        'cloud_id' => $context['cloud_id'] ?? null,
                        'source' => $context['source'] ?? 'pull',
                        'raw_payload' => $record,
                    ]
                );

                $log->wasRecentlyCreated ? $result['created']++ : $result['updated']++;
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'pin' => $record['pin'] ?? null,
                    'scan_date' => $record['scan_date'] ?? $record['scan_time'] ?? null,
                    'message' => $e->getMessage(),
                ];

                Log::warning('Failed to store Fingerspot attendance log', [
                    'record' => $record,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function extractRecords(array $payload): array
    {
        $data = $payload['data']
            ?? $payload['attlog']
            ?? $payload['attlogs']
            ?? $payload['records']
            ?? null;

        if (is_string($data)) {
            $decoded = json_decode($data, true);
            $data = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        if ($this->looksLikeAttendanceRecord($payload)) {
            return [$payload];
        }

        if (! is_array($data)) {
            return [];
        }

        if ($this->looksLikeAttendanceRecord($data)) {
            return [$data];
        }

        return array_values(array_filter($data, fn ($item) => is_array($item)));
    }

    private function normalizeRecord(array $record): ?array
    {
        $pin = trim((string) ($record['pin'] ?? $record['nik'] ?? $record['user_id'] ?? ''));
        $scanDate = $record['scan_date'] ?? $record['scan_time'] ?? $record['date_time'] ?? $record['timestamp'] ?? null;

        if ($pin === '' || empty($scanDate)) {
            return null;
        }

        try {
            $scanDate = Carbon::parse($scanDate)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }

        return [
            'pin' => $pin,
            'scan_date' => $scanDate,
            'verify' => isset($record['verify'])
                ? (string) $record['verify']
                : (isset($record['verification']) ? (string) $record['verification'] : null),
            'status_scan' => isset($record['status_scan'])
                ? (string) $record['status_scan']
                : (isset($record['status']) ? (string) $record['status'] : null),
        ];
    }

    private function looksLikeAttendanceRecord(array $payload): bool
    {
        return array_key_exists('pin', $payload)
            && (array_key_exists('scan_date', $payload) || array_key_exists('scan_time', $payload));
    }
}
