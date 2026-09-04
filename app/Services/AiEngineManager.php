<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiEngineManager
{
    public function __construct(
        private readonly NineRouterChatService $nineRouter,
        private readonly GeminiChatService $gemini,
        private readonly OpenRouterChatService $openRouter
    ) {}

    /**
     * Menjalankan chat ke AI Engine dengan prioritas:
     * 1. 9Router Local Proxy (Model Combo GEMINI - Prioritas Utama)
     * 2. Google Gemini Flash (Cadangan 1 - Direct Multi-Key Round Robin)
     * 3. OpenRouter (Cadangan 2 - Fallback jika 9Router & Gemini limit/error)
     */
    public function chat(string $prompt, string $systemPrompt = '', array $history = []): ?string
    {
        // 1. Coba Engine Utama: 9Router Proxy Lokal dengan Combo GEMINI
        if (filled(config('services.ninerouter.api_key')) || filled(config('services.ninerouter.base_url'))) {
            try {
                $nineRouterRes = $this->nineRouter->chat($prompt, $systemPrompt, $history);
                if ($nineRouterRes !== null && trim($nineRouterRes) !== '') {
                    return trim($nineRouterRes);
                }
            } catch (\Throwable $e) {
                Log::warning("9Router AI Engine failed, switching to backup: " . $e->getMessage());
            }
        }

        // 2. Coba Cadangan 1: Gemini Multi-Key Round Robin
        if (filled(config('services.gemini.api_key')) || ! empty(config('services.gemini.api_keys'))) {
            try {
                $geminiRes = $this->gemini->chat($prompt, $systemPrompt, $history);
                if ($geminiRes !== null && trim($geminiRes) !== '') {
                    return trim($geminiRes);
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini AI Engine failed, switching to backup: " . $e->getMessage());
            }
        }

        // 3. Fallback ke Cadangan 2: OpenRouter
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
