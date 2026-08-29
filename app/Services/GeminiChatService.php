<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiChatService
{
    /**
     * Mengirim permintaan chat ke Google Gemini dengan rotasi Round Robin antar API Key
     * dan otomatis fallback ke key berikutnya jika ada key yang limit / error.
     */
    public function chat(string $prompt, string $systemPrompt, array $history = []): ?string
    {
        $keysPool = $this->getRotatedApiKeys();

        if (empty($keysPool)) {
            Log::warning('Gemini chat skipped: No GEMINI_API_KEYS configured');
            return null;
        }

        $timeout = min((int) config('services.gemini.timeout', 8), 12);
        $primaryModel = trim((string) config('services.gemini.model', 'gemini-2.5-flash'));
        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'gemini-2.5-flash',
            'gemini-3.1-flash-lite',
            'gemini-1.5-flash',
            'gemini-3.6-flash',
        ]));

        $contents = [];
        if (! empty($history)) {
            foreach ($history as $h) {
                if (isset($h['role'], $h['parts'])) {
                    $contents[] = $h;
                }
            }
        }
        $contents[] = [
            'role' => 'user',
            'parts' => [
                ['text' => $prompt],
            ],
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1024,
            ],
        ];

        if (trim($systemPrompt) !== '') {
            $payload['system_instruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        // Coba setiap API Key dalam rotasi Round-Robin
        foreach ($keysPool as $keyIndex => $apiKey) {
            $maskedKey = substr($apiKey, 0, 6) . '...' . substr($apiKey, -4);

            foreach ($modelsToTry as $model) {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

                try {
                    $response = Http::timeout($timeout)
                        ->withHeaders([
                            'Content-Type' => 'application/json',
                        ])
                        ->post($endpoint, $payload);

                    if (! $response->successful()) {
                        Log::warning("Gemini API ({$model}) using key [{$maskedKey}] failed with status {$response->status()}", [
                            'response' => $response->json() ?: $response->body(),
                        ]);

                        // Jika rate limit 429 atau quota exceeded, langsung ganti API key berikutnya
                        if ($response->status() === 429 || $response->status() === 403) {
                            break; // Lanjut ke key berikutnya
                        }

                        continue; // Coba model fallback berikutnya untuk key ini
                    }

                    $result = $response->json();
                    $content = (string) data_get($result, 'candidates.0.content.parts.0.text', '');
                    $content = trim($content);

                    if ($content !== '') {
                        return $content;
                    }
                } catch (Throwable $exception) {
                    Log::warning("Gemini API ({$model}) with key [{$maskedKey}] exception: " . $exception->getMessage());
                }
            }
        }

        return null;
    }

    /**
     * Mengambil daftar API Key Gemini yang sudah dirotasi secara adil (Round Robin).
     * Contoh 3 Key:
     * - Request 1: [Key1, Key2, Key3]
     * - Request 2: [Key2, Key3, Key1]
     * - Request 3: [Key3, Key1, Key2]
     */
    public function getRotatedApiKeys(): array
    {
        $keys = config('services.gemini.api_keys', []);

        if (empty($keys) && filled(config('services.gemini.api_key'))) {
            $keys = [trim((string) config('services.gemini.api_key'))];
        }

        $keys = array_values(array_unique(array_filter($keys)));
        $count = count($keys);

        if ($count <= 1) {
            return $keys;
        }

        // Ambil dan naikkan turn round-robin counter
        $turn = (int) Cache::get('gemini_api_key_round_robin_counter', 0);
        Cache::put('gemini_api_key_round_robin_counter', ($turn + 1) % 10000, now()->addDays(7));

        $startIndex = $turn % $count;
        $rotated = [];
        for ($i = 0; $i < $count; $i++) {
            $rotated[] = $keys[($startIndex + $i) % $count];
        }

        return $rotated;
    }
}
