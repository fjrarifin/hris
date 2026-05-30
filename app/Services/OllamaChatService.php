<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OllamaChatService
{
    public function chat(string $prompt, string $systemPrompt): ?string
    {
        $baseUrl = rtrim(trim((string) config('services.ollama.url')), '/');
        $model = trim((string) config('services.ollama.model'));

        if ($baseUrl === '' || $model === '') {
            Log::warning('Ollama chat skipped: configuration incomplete');

            return null;
        }

        try {
            $response = Http::timeout((int) config('services.ollama.timeout', 60))
                ->post($baseUrl.'/api/chat', [
                    'model' => $model,
                    'stream' => false,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'options' => [
                        'temperature' => 0.2,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Ollama chat failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                return null;
            }

            $content = (string) data_get($response->json(), 'message.content', data_get($response->json(), 'response', ''));
            $content = trim(preg_replace('/<think>.*?<\/think>/is', '', $content) ?? $content);

            return $content !== '' ? $content : null;
        } catch (Throwable $exception) {
            Log::warning('Ollama chat exception', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
