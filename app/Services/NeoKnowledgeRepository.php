<?php

namespace App\Services;

use App\Models\NeoKnowledge;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Sumber tunggal basic knowledge Neo. Membaca seksi aktif dari tabel t_neo_knowledge,
 * dan fallback ke file resources/knowledge/hris_employee_manual.md bila tabel kosong/belum ada.
 */
class NeoKnowledgeRepository
{
    public const CACHE_KEY = 'hris_employee_manual_kb';

    /** Batas peringatan ukuran gabungan knowledge yang masuk ke prompt AI. */
    public const SOFT_CHAR_LIMIT = 30000;

    public static function compiled(): string
    {
        return Cache::remember(self::CACHE_KEY, 3600, function (): string {
            try {
                if (Schema::hasTable('t_neo_knowledge')) {
                    $sections = NeoKnowledge::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->pluck('content');

                    if ($sections->isNotEmpty()) {
                        return $sections->implode("\n\n---\n\n");
                    }
                }
            } catch (Throwable) {
                // Jatuh ke file bila database bermasalah.
            }

            $path = resource_path('knowledge/hris_employee_manual.md');

            return file_exists($path) ? (string) file_get_contents($path) : '';
        });
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

