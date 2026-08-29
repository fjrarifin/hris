<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AiEngineManager
{
    public function __construct(
        private readonly OpenRouterChatService $openRouter,
        private readonly TokenRouterChatService $tokenRouter,
        private readonly GeminiChatService $gemini
    ) {}

    /**
     * Menjalankan chat ke salah satu AI Engine dengan skema Round-Robin bergantian (OpenRouter, TokenRouter, Gemini).
     * Jika engine yang sedang giliran gagal / kosong / rate limit, otomatis fallback ke engine berikutnya.
     */
    public function chat(string $prompt, string $systemPrompt = '', array $history = []): ?string
    {
        $engines = $this->getEngineRotation();

        foreach ($engines as $engineName) {
            $result = match ($engineName) {
                'openrouter' => $this->openRouter->chat($prompt, $systemPrompt, $history),
                'tokenrouter' => $this->tokenRouter->chat($prompt, $systemPrompt, $history),
                'gemini' => $this->gemini->chat($prompt, $systemPrompt, $history),
                default => null,
            };

            if ($result !== null && trim($result) !== '') {
                Log::info("AI Chat Response served by engine: [{$engineName}]");
                return trim($result);
            }
        }

        return null;
    }

    /**
     * Menentukan rotasi engine saat ini dan menyusun urutan fallback.
     * Contoh: Putaran 0 -> [openrouter, tokenrouter, gemini]
     *         Putaran 1 -> [tokenrouter, gemini, openrouter]
     *         Putaran 2 -> [gemini, openrouter, tokenrouter]
     */
    public function getEngineRotation(): array
    {
        $allEngines = ['openrouter', 'tokenrouter', 'gemini'];

        $availableEngines = array_values(array_filter($allEngines, function ($engine) {
            return match ($engine) {
                'openrouter' => filled(config('services.openrouter.api_key')),
                'tokenrouter' => filled(config('services.tokenrouter.api_key')),
                'gemini' => filled(config('services.gemini.api_key')),
                default => false,
            };
        }));

        if (empty($availableEngines)) {
            return ['openrouter', 'gemini'];
        }

        $count = count($availableEngines);
        if ($count <= 1) {
            return $availableEngines;
        }

        $currentTurn = (int) Cache::get('ai_engine_round_robin_counter', 0);
        Cache::put('ai_engine_round_robin_counter', ($currentTurn + 1) % 10000, now()->addDays(7));
        $startIndex = $currentTurn % $count;

        $rotated = [];
        for ($i = 0; $i < $count; $i++) {
            $rotated[] = $availableEngines[($startIndex + $i) % $count];
        }

        return $rotated;
    }
}
