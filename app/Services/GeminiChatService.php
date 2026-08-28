<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiChatService
{
    public function chat(string $prompt, string $systemPrompt, array $history = []): ?string
    {
        $apiKey = trim((string) config('services.gemini.api_key'));
        $timeout = min((int) config('services.gemini.timeout', 5), 8);

        if ($apiKey === '') {
            Log::warning('Gemini chat skipped: GEMINI_API_KEY is not configured');

            return null;
        }

        $primaryModel = trim((string) config('services.gemini.model', 'gemini-3.1-flash-lite'));
        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'gemini-3.1-flash-lite',
            'gemini-3-flash-preview',
            'gemini-3.1-flash-lite-preview',
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

        foreach ($modelsToTry as $model) {
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($endpoint, $payload);

                if (! $response->successful()) {
                    Log::warning("Gemini API ({$model}) call failed with status {$response->status()}, trying next model if available");
                    continue;
                }

                $result = $response->json();
                $content = (string) data_get($result, 'candidates.0.content.parts.0.text', '');
                $content = trim($content);

                if ($content !== '') {
                    return $content;
                }
            } catch (Throwable $exception) {
                Log::warning("Gemini API ({$model}) exception: " . $exception->getMessage());
            }
        }

        return null;
    }
}
