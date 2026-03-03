<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class WhatsAppService
{
    protected string $baseUrl;
    protected string $deviceId;

    public function __construct()
    {
        $this->baseUrl  = config('services.whatsapp.url');
        $this->deviceId = config('services.whatsapp.device_id');
    }

    protected function request()
    {
        return Http::withHeaders([
            'X-Device-Id' => $this->deviceId,
            'Accept'      => 'application/json',
        ])->timeout(5);
    }

    public function sendMessage(string $phone, string $message): bool
    {
        $phone = $this->normalizePhone($phone);

        Log::info('OTP WA - mulai kirim', [
            'phone' => $phone,
        ]);

        /** @var Response $response */
        $response = $this->request()->post(
            $this->baseUrl . '/send/message',
            [
                'phone'   => $phone,   // 🔥 WAJIB phone
                'message' => $message,
            ]
        );

        Log::info('WA SEND OTP', [
            'phone'    => $phone,
            'status'   => $response->status(),
            'response' => $response->json(),
        ]);

        return $response->successful();
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '08')) {
            return '62' . substr($phone, 1);
        }

        if (str_starts_with($phone, '8')) {
            return '62' . $phone;
        }

        return $phone;
    }
}