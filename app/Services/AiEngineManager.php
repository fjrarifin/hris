<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiEngineManager
{
    public function __construct(
        private readonly GeminiChatService $gemini,
        private readonly OpenRouterChatService $openRouter
    ) {}

    /**
     * Menjalankan chat ke AI Engine dengan prioritas:
     * 1. Google Gemini Flash (Utama - Super Cepat & Natural)
     * 2. OpenRouter (Cadangan / Fallback jika Gemini limit/error)
     */
    public function chat(string $prompt, string $systemPrompt = '', array $history = []): ?string
    {
        // 1. Coba Engine Utama: Gemini
        if (filled(config('services.gemini.api_key'))) {
            try {
                $geminiRes = $this->gemini->chat($prompt, $systemPrompt, $history);
                if ($geminiRes !== null && trim($geminiRes) !== '') {
                    return trim($geminiRes);
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini AI Engine failed, switching to backup: " . $e->getMessage());
            }
        }

        // 2. Fallback ke Engine Cadangan: OpenRouter
        if (filled(config('services.openrouter.api_key'))) {
            try {
                $openRouterRes = $this->openRouter->chat($prompt, $systemPrompt, $history);
                if ($openRouterRes !== null && trim($openRouterRes) !== '') {
                    return trim($openRouterRes);
                }
            } catch (\Throwable $e) {
                Log::warning("OpenRouter Backup AI Engine failed: " . $e->getMessage());
            }
        }

        return null;
    }
}
