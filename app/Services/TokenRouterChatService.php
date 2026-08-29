<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TokenRouterChatService
{
    public function chat(string $prompt, string $systemPrompt, array $history = []): ?string
    {
        $apiKey = trim((string) config('services.tokenrouter.api_key'));
        $baseUrl = rtrim((string) config('services.tokenrouter.base_url', 'https://api.tokenrouter.com/v1'), '/');
        $primaryModel = trim((string) config('services.tokenrouter.model', 'z-ai/glm-5.3-free'));
        $timeout = min((int) config('services.tokenrouter.timeout', 12), 20);

        if ($apiKey === '') {
            return null;
        }

        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'z-ai/glm-5.3-free',
            'z-ai/glm-5.3',
        ]));

        $messages = [];
        if (trim($systemPrompt) !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        if (! empty($history)) {
            foreach ($history as $h) {
                $role = data_get($h, 'role') === 'model' ? 'assistant' : data_get($h, 'role', 'user');
                $text = data_get($h, 'parts.0.text', data_get($h, 'content', ''));
                if ($text !== '') {
                    $messages[] = [
                        'role' => $role,
                        'content' => $text,
                    ];
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $prompt,
        ];

        foreach ($modelsToTry as $model) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => 'Bearer ' . $apiKey,
                        'Content-Type' => 'application/json',
                    ])
                    ->post("{$baseUrl}/chat/completions", [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => 0.3,
                        'max_tokens' => 1024,
                    ]);

                if (! $response->successful()) {
                    Log::warning("TokenRouter API ({$model}) failed with status {$response->status()}", [
                        'response' => $response->json() ?: $response->body(),
                    ]);
                    continue;
                }

                $result = $response->json();
                $content = (string) data_get($result, 'choices.0.message.content', '');

                // Bersihkan tag <think>...</think> tertutup
                $content = preg_replace('/<think>.*?<\/think>/is', '', $content) ?? $content;
                // Bersihkan jika ada <think> tanpa penutup di awal
                $content = preg_replace('/^<think>.*$/is', '', $content) ?? $content;
                // Bersihkan jika model menulis "Here's a thinking process:..."
                if (preg_match('/(?:Here\'s a thinking process|Thinking Process|Let\'s think)[\s\S]*?\n\n([\s\S]+)$/i', $content, $m)) {
                    $content = $m[1];
                }

                $content = trim($content);

                if ($content !== '') {
                    return $content;
                }
            } catch (Throwable $e) {
                Log::warning("TokenRouter API ({$model}) exception: " . $e->getMessage());
            }
        }

        return null;
    }
}
