<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebasePushService
{
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokens = MobileDeviceToken::query()->where('user_id', $userId)->pluck('token');

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }
    }

    private function send(string $token, string $title, string $body, array $data): void
    {
        $projectId = config('services.firebase.project_id');
        $accessToken = $this->accessToken();

        if (! $projectId || ! $accessToken) {
            return;
        }

        $response = Http::withToken($accessToken)->post(
            "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
            [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => collect($data)
                        ->map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value))
                        ->all(),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'channel_id' => 'hris_requests',
                            'click_action' => 'OPEN_HRIS_NOTIFICATION',
                        ],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            Log::warning('Firebase push failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function accessToken(): ?string
    {
        return Cache::remember('firebase_push_access_token', 50 * 60, function (): ?string {
            $credentials = $this->credentials();

            if (! $credentials || empty($credentials['client_email']) || empty($credentials['private_key'])) {
                return null;
            }

            $now = time();
            $jwt = $this->jwt([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key']);

            if (! $jwt) {
                return null;
            }

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            return $response->json('access_token');
        });
    }

    private function credentials(): ?array
    {
        $json = config('services.firebase.credentials_json');
        $path = config('services.firebase.credentials_file');

        if ($json) {
            return json_decode($json, true);
        }

        if ($path && is_file($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return null;
    }

    private function jwt(array $claims, string $privateKey): ?string
    {
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64Url(json_encode($claims));
        $unsigned = "{$header}.{$payload}";
        $signature = '';

        if (! openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
