<?php

namespace App\Services;

use App\Models\FingerspotUserTemplate;
use App\Models\Karyawan;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FingerspotUserinfoService
{
    public function clouds(): array
    {
        $clouds = collect(config('fingerspot.clouds', []))
            ->filter(fn (array $cloud): bool => filled($cloud['id'] ?? null))
            ->map(fn (array $cloud): array => [
                'id' => (string) $cloud['id'],
                'name' => filled($cloud['name'] ?? null) ? (string) $cloud['name'] : (string) $cloud['id'],
            ]);

        if ($clouds->isEmpty() && filled(config('fingerspot.default_cloud_id'))) {
            $clouds->push([
                'id' => (string) config('fingerspot.default_cloud_id'),
                'name' => 'Mesin Utama',
            ]);
        }

        return $clouds
            ->unique('id')
            ->values()
            ->all();
    }

    public function pullAllBiometrics(?string $cloudId = null): array
    {
        $targetClouds = $cloudId
            ? collect($this->clouds())->where('id', $cloudId)->values()
            : collect($this->clouds());

        if ($targetClouds->isEmpty()) {
            throw new InvalidArgumentException('Mesin absensi tidak ditemukan.');
        }

        $employees = Karyawan::query()
            ->whereNotNull('pin')
            ->where('pin', '!=', '')
            ->get();

        $successCount = 0;
        $failedCount = 0;
        $details = [];

        foreach ($employees as $employee) {
            foreach ($targetClouds as $cloud) {
                try {
                    $res = $this->pullEmployeeUserinfo($employee, $cloud['id']);
                    if ($res['ok'] ?? false) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                    $details[] = [
                        'pin' => $employee->pin,
                        'name' => $employee->nama_karyawan,
                        'cloud_id' => $cloud['id'],
                        'ok' => $res['ok'] ?? false,
                    ];
                } catch (\Throwable $e) {
                    $failedCount++;
                    $details[] = [
                        'pin' => $employee->pin,
                        'name' => $employee->nama_karyawan,
                        'cloud_id' => $cloud['id'],
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'total_employees' => $employees->count(),
            'total_clouds' => $targetClouds->count(),
            'commands_sent' => $successCount + $failedCount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'details' => $details,
        ];
    }

    public function sendAllEmployees(?string $cloudId = null): array
    {
        $targetClouds = $cloudId
            ? collect($this->clouds())->where('id', $cloudId)->values()
            : collect($this->clouds());

        if ($targetClouds->isEmpty()) {
            throw new InvalidArgumentException('Mesin absensi tidak ditemukan.');
        }

        $employees = Karyawan::query()
            ->whereNotNull('pin')
            ->where('pin', '!=', '')
            ->get();

        $successCount = 0;
        $failedCount = 0;
        $details = [];

        foreach ($employees as $employee) {
            foreach ($targetClouds as $cloud) {
                try {
                    $res = $this->sendEmployee($employee, $cloud['id']);
                    if ($res['ok'] ?? false) {
                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                    $details[] = [
                        'pin' => $employee->pin,
                        'name' => $employee->nama_karyawan,
                        'cloud_id' => $cloud['id'],
                        'ok' => $res['ok'] ?? false,
                    ];
                } catch (\Throwable $e) {
                    $failedCount++;
                    $details[] = [
                        'pin' => $employee->pin,
                        'name' => $employee->nama_karyawan,
                        'cloud_id' => $cloud['id'],
                        'ok' => false,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'total_employees' => $employees->count(),
            'total_clouds' => $targetClouds->count(),
            'commands_sent' => $successCount + $failedCount,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'details' => $details,
        ];
    }

    public function pullEmployeeUserinfo(Karyawan $employee, string $cloudId): array
    {
        $cloud = collect($this->clouds())->firstWhere('id', $cloudId);

        if (! $cloud) {
            throw new InvalidArgumentException('Mesin absensi tidak terdaftar di konfigurasi Fingerspot.');
        }

        $pin = trim((string) $employee->pin);

        if ($pin === '') {
            throw new InvalidArgumentException('PIN absensi karyawan belum diisi.');
        }

        if (blank(config('fingerspot.base_url')) || blank(config('fingerspot.api_token'))) {
            throw new InvalidArgumentException('Konfigurasi Fingerspot belum lengkap.');
        }

        $payload = [
            'trans_id' => $this->transId('GETUSER'),
            'cloud_id' => $cloud['id'],
            'pin' => $pin,
        ];

        $response = Http::withToken((string) config('fingerspot.api_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim((string) config('fingerspot.base_url'), '/').'/get_userinfo', $payload);

        $responseJson = $response->json() ?? [];

        Log::info('Fingerspot get_userinfo command sent', [
            'ok' => $response->successful(),
            'http_status' => $response->status(),
            'cloud_id' => $cloud['id'],
            'employee_nik' => $employee->nik,
            'pin' => $pin,
            'trans_id' => $payload['trans_id'],
            'response' => $responseJson,
        ]);

        if ($response->successful() && is_array($responseJson)) {
            $this->saveUserInfoPayload($responseJson, $cloud['id']);
        }

        return [
            'ok' => $response->successful(),
            'http_status' => $response->status(),
            'cloud' => $cloud,
            'trans_id' => $payload['trans_id'],
            'request_payload' => $payload,
            'response' => $responseJson,
            'raw_response' => $response->body(),
        ];
    }

    public function saveUserInfoPayload(array $payload, ?string $defaultCloudId = null): ?FingerspotUserTemplate
    {
        $cloudId = Arr::get($payload, 'cloud_id') ?: $defaultCloudId;
        $rawItems = Arr::get($payload, 'data') ?: $payload;

        $items = (is_array($rawItems) && isset($rawItems[0]) && is_array($rawItems[0]))
            ? $rawItems
            : [$rawItems];

        $lastSaved = null;

        foreach ($items as $data) {
            if (! is_array($data)) {
                continue;
            }

            $pin = trim((string) (Arr::get($data, 'pin') ?: Arr::get($payload, 'pin')));

            if ($pin === '') {
                continue;
            }

            $name = trim((string) (Arr::get($data, 'name') ?: Arr::get($data, 'user_name') ?: ''));
            $privilege = (string) (Arr::get($data, 'privilege') ?? '0');
            $password = (string) (Arr::get($data, 'password') ?? '');
            $card = (string) (Arr::get($data, 'card') ?: Arr::get($data, 'rfid') ?: '');
            $template = Arr::get($data, 'template') ?: Arr::get($data, 'fingerprint');

            if (is_array($template) || is_object($template)) {
                $template = json_encode($template);
            }

            $userTemplate = FingerspotUserTemplate::updateOrCreate(
                ['pin' => $pin],
                [
                    'name' => $name ?: null,
                    'cloud_id' => $cloudId,
                    'privilege' => $privilege,
                    'password' => $password,
                    'card' => $card ?: null,
                    'template' => $template ? (string) $template : null,
                    'raw_data' => $payload,
                    'last_pulled_at' => now(),
                ]
            );

            Log::info('Fingerspot user template saved to DB', [
                'pin' => $pin,
                'name' => $name,
                'cloud_id' => $cloudId,
                'has_template' => ! empty($template),
                'card' => $card,
            ]);

            $lastSaved = $userTemplate;
        }

        return $lastSaved;
    }

    public function getEmployeeTemplate(Karyawan $employee): array
    {
        $pin = trim((string) $employee->pin);

        if ($pin === '') {
            return [
                'has_template' => false,
                'template' => null,
            ];
        }

        $stored = FingerspotUserTemplate::where('pin', $pin)->first();

        return [
            'has_template' => $stored && ! empty($stored->template),
            'template' => $stored,
            'last_pulled_at' => $stored?->last_pulled_at?->format('d M Y H:i:s'),
            'source_cloud_id' => $stored?->cloud_id,
            'card' => $stored?->card,
        ];
    }

    public function sendEmployee(Karyawan $employee, string $cloudId): array
    {
        $cloud = collect($this->clouds())->firstWhere('id', $cloudId);

        if (! $cloud) {
            throw new InvalidArgumentException('Mesin absensi tidak terdaftar di konfigurasi Fingerspot.');
        }

        $pin = trim((string) $employee->pin);
        $name = trim((string) $employee->nama_karyawan);

        if ($pin === '') {
            throw new InvalidArgumentException('PIN absensi karyawan belum diisi.');
        }

        if ($name === '') {
            throw new InvalidArgumentException('Nama karyawan belum diisi.');
        }

        if (blank(config('fingerspot.base_url')) || blank(config('fingerspot.api_token'))) {
            throw new InvalidArgumentException('Konfigurasi Fingerspot belum lengkap.');
        }

        $stored = FingerspotUserTemplate::where('pin', $pin)->first();

        $payload = [
            'trans_id' => $this->transId('SETUSER'),
            'cloud_id' => $cloud['id'],
            'data' => [
                'pin' => $pin,
                'name' => $name,
                'privilege' => $stored?->privilege ?? '0',
                'password' => $stored?->password ?? '',
                'rfid' => $stored?->card ?? '',
                'template' => $stored?->template ?? '',
            ],
        ];

        $response = Http::withToken((string) config('fingerspot.api_token'))
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(rtrim((string) config('fingerspot.base_url'), '/').'/set_userinfo', $payload);

        $result = [
            'ok' => $response->successful(),
            'http_status' => $response->status(),
            'cloud' => $cloud,
            'trans_id' => $payload['trans_id'],
            'request_payload' => $payload,
            'has_biometric_template' => ! empty($stored?->template),
            'response' => $response->json(),
            'raw_response' => $response->body(),
        ];

        Log::info('Fingerspot set_userinfo command sent', [
            'ok' => $result['ok'],
            'http_status' => $result['http_status'],
            'cloud_id' => $cloud['id'],
            'employee_nik' => $employee->nik,
            'pin' => $pin,
            'has_template' => ! empty($stored?->template),
            'trans_id' => $payload['trans_id'],
        ]);

        return $result;
    }

    private function transId(string $prefix = 'SETUSER'): string
    {
        return $prefix.'-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6));
    }
}
