<?php

use App\Services\NeoKnowledgeRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('t_neo_knowledge')) {
            $section = $this->extractManualSection('3');
            if ($section !== null) {
                $now = now();
                $targetKey = '3-public-holiday-ph-extra-off-eo';

                $existing = DB::table('t_neo_knowledge')
                    ->where('section_key', $targetKey)
                    ->orWhere('title', 'like', '3. Public Holiday%')
                    ->first();

                if ($existing) {
                    DB::table('t_neo_knowledge')
                        ->where('id', $existing->id)
                        ->update([
                            'title' => $section['title'],
                            'content' => $section['content'],
                            'is_active' => true,
                            'updated_by' => 'system',
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('t_neo_knowledge')->insert([
                        'section_key' => $targetKey,
                        'title' => $section['title'],
                        'content' => $section['content'],
                        'sort_order' => 30,
                        'is_active' => true,
                        'updated_by' => 'system',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                NeoKnowledgeRepository::forget();
            }
        }

        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'it-neo-knowledge')
                ->update([
                    'label' => 'Nova Knowledge',
                    'path' => '/it/nova-knowledge',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')
                ->where('key', 'it-neo-knowledge')
                ->update([
                    'label' => 'Neo Knowledge',
                    'path' => '/it/neo-knowledge',
                    'updated_at' => now(),
                ]);
        }

        NeoKnowledgeRepository::forget();
    }

    private function extractManualSection(string $prefix): ?array
    {
        $path = resource_path('knowledge/hris_employee_manual.md');
        if (! file_exists($path)) {
            return null;
        }

        $raw = str_replace("\r\n", "\n", (string) file_get_contents($path));
        $chunks = preg_split('/^(?=## )/m', $raw) ?: [];

        foreach ($chunks as $chunk) {
            $content = trim((string) preg_replace('/\n*-{3,}\s*$/', '', trim($chunk)));
            if ($content === '' || ! str_starts_with($content, '## ')) {
                continue;
            }

            $title = trim((string) preg_replace('/^##\s*/', '', (string) strtok($content, "\n")));
            if (preg_match('/^' . preg_quote($prefix, '/') . '\.\s/', $title)) {
                return [
                    'title' => $title,
                    'content' => $content,
                ];
            }
        }

        return null;
    }
};
