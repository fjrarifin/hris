<?php

namespace App\Services;

use App\Models\Karyawan;
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

        $payload = [
            'trans_id' => $this->transId(),
            'cloud_id' => $cloud['id'],
            'data' => [
                'pin' => $pin,
                'name' => $name,
                'privilege' => '1',
                'password' => '',
                'rfid' => '',
                'template' => '',
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
            'response' => $response->json(),
            'raw_response' => $response->body(),
        ];

        Log::info('Fingerspot set_userinfo command sent', [
            'ok' => $result['ok'],
            'http_status' => $result['http_status'],
            'cloud_id' => $cloud['id'],
            'employee_nik' => $employee->nik,
            'pin' => $pin,
            'trans_id' => $payload['trans_id'],
        ]);

        return $result;
    }

    private function transId(): string
    {
        return 'SETUSER-'.now()->format('YmdHisv').'-'.Str::upper(Str::random(6));
    }
}
