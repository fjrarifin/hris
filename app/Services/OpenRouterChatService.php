<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenRouterChatService
{
    public function chat(string $prompt, string $systemPrompt, array $history = []): ?string
    {
        $apiKey = trim((string) config('services.openrouter.api_key'));
        $timeout = min((int) config('services.openrouter.timeout', 8), 12);

        if ($apiKey === '') {
            return null;
        }

        $primaryModel = trim((string) config('services.openrouter.model', 'meta-llama/llama-3.3-70b-instruct:free'));
        $modelsToTry = array_unique(array_filter([
            $primaryModel,
            'meta-llama/llama-3.3-70b-instruct:free',
            'deepseek/deepseek-chat:free',
            'mistralai/mistral-small-24b-instruct-2501:free',
            'google/gemini-2.0-flash-exp:free',
            'meta-llama/llama-3.1-8b-instruct:free',
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
                        'HTTP-Referer' => 'https://hr.hompimplay.id',
                        'X-Title' => 'HRIS WhatsApp AI Agent',
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => 0.3,
                        'max_tokens' => 1024,
                    ]);

                if (! $response->successful()) {
                    Log::warning("OpenRouter API ({$model}) failed with status {$response->status()}", [
                        'response' => $response->json() ?: $response->body(),
                    ]);
                    continue;
                }

                $result = $response->json();
                $content = (string) data_get($result, 'choices.0.message.content', '');
                $content = trim(preg_replace('/<think>.*?<\/think>/is', '', $content) ?? $content);

                if ($content !== '') {
                    return $content;
                }
            } catch (Throwable $e) {
                Log::warning("OpenRouter API ({$model}) exception: " . $e->getMessage());
            }
        }

        return null;
    }
}
