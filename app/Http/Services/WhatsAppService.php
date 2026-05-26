<?php

namespace App\Http\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $baseUrl;

    protected string $deviceId;

    public function __construct()
    {
        $this->baseUrl = rtrim(trim((string) config('services.whatsapp.url')), '/');
        $this->deviceId = trim((string) config('services.whatsapp.device_id'));
    }

    protected function request()
    {
        $request = Http::withHeaders([
            'X-Device-Id' => $this->deviceId,
            'Accept' => 'application/json',
        ])->timeout(10);

        $username = trim((string) config('services.whatsapp.username'));
        $password = trim((string) config('services.whatsapp.password'));

        if ($username && $password) {
            $request = $request->withBasicAuth($username, $password);
        }

        return $request;
    }

    public function sendMessage(string $phone, string $message): bool
    {
        if ($this->baseUrl === '' || $this->deviceId === '') {
            Log::error('WA - konfigurasi pengiriman belum lengkap', [
                'has_url' => $this->baseUrl !== '',
                'has_device_id' => $this->deviceId !== '',
            ]);

            return false;
        }

        $phone = $this->normalizePhone($phone);

        Log::info('WA - mulai kirim', ['phone' => $phone]);

        /** @var Response $response */
        $response = $this->request()->post(
            $this->baseUrl.'/send/message',
            [
                'phone' => $phone,
                'message' => $message,
            ]
        );

        Log::info('WA - respons pengiriman', [
            'phone' => $phone,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return $response->successful();
    }

    private function normalizePhone(string $phone): string
    {
        if (str_contains($phone, '@g.us')) {
            return $phone;
        }

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '08')) {
            return '62'.substr($phone, 1);
        }

        if (str_starts_with($phone, '8')) {
            return '62'.$phone;
        }

        return $phone;
    }
}
