<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class NineRouterChatService
{
    /**
     * Mengirim permintaan chat ke 9Router Proxy lokal dengan combo model GEMINI.
     */
    public function chat(string $prompt, string $systemPrompt, array $history = []): ?string
    {
        $apiKey = trim((string) config('services.ninerouter.api_key', 'sk-0de72b6d36d00d02-ub1zv4-b8f5d965'));
        $baseUrl = rtrim((string) config('services.ninerouter.base_url', 'http://localhost:20128/v1'), '/');
        $model = trim((string) config('services.ninerouter.model', 'GEMINI'));
        $timeout = min((int) config('services.ninerouter.timeout', 20), 40);

        if ($apiKey === '' || $baseUrl === '') {
            return null;
        }

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
                    'stream' => false,
                ]);

            if (! $response->successful()) {
                Log::warning("9Router API ({$model}) failed with status {$response->status()}", [
                    'response' => $response->json() ?: $response->body(),
                ]);
                return null;
            }

            $result = $response->json();
            $content = (string) data_get($result, 'choices.0.message.content', '');

            // Bersihkan tag <think>...</think>
            $content = preg_replace('/<think>.*?<\/think>/is', '', $content) ?? $content;
            $content = preg_replace('/^<think>.*$/is', '', $content) ?? $content;
            if (preg_match('/(?:Here\'s a thinking process|Thinking Process|Let\'s think)[\s\S]*?\n\n([\s\S]+)$/i', $content, $m)) {
                $content = $m[1];
            }

            $content = trim($content);

            if ($content !== '') {
                return $content;
            }
        } catch (Throwable $e) {
            Log::warning("9Router API ({$model}) exception: " . $e->getMessage());
        }

        return null;
    }
}
