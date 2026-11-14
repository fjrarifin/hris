<?php

use App\Services\NeoKnowledgeRepository;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('t_neo_knowledge')) {
            return;
        }

        $path = resource_path('knowledge/hris_employee_manual.md');
        if (! file_exists($path)) {
            return;
        }

        $raw = str_replace("\r\n", "\n", (string) file_get_contents($path));
        $chunks = preg_split('/^(?=## )/m', $raw) ?: [];
        $byHeadingPrefix = [];

        foreach ($chunks as $chunk) {
            $content = trim((string) preg_replace('/\n*-{3,}\s*$/', '', trim($chunk)));
            if ($content === '' || ! str_starts_with($content, '## ')) {
                continue;
            }

            $title = trim((string) preg_replace('/^##\s*/', '', (string) strtok($content, "\n")));
            if (preg_match('/^(\d+)\.\s/', $title, $m)) {
                $byHeadingPrefix[$m[1]] = [
                    'title' => $title,
                    'content' => $content,
                ];
            }
        }

        $targets = [
            '5' => ['key' => '5-pengajuan-lembur-spl-surat-perintah-lembur', 'sort_order' => 50],
            '6' => ['key' => '6-pengaturan-jadwal-kerja-tim-khusus-supervisor', 'sort_order' => 60],
            '10' => ['key' => '10-tingkatan-wewenang-user-level-rbac', 'sort_order' => 100],
            '11' => ['key' => '11-identitas-kemampuan-neo-perintah-yang-bisa-dieksekusi', 'sort_order' => 110],
            '12' => ['key' => '12-sumber-data-real-time-knowledge-dinamis', 'sort_order' => 120],
            '13' => ['key' => '13-panduan-navigasi-menu-portal-hris-karyawan-atasan', 'sort_order' => 130],
        ];

        $now = now();

        foreach ($targets as $prefix => $meta) {
            if (! isset($byHeadingPrefix[$prefix])) {
                continue;
            }

            $section = $byHeadingPrefix[$prefix];
            $exists = DB::table('t_neo_knowledge')->where('section_key', $meta['key'])->exists();

            if ($exists) {
                DB::table('t_neo_knowledge')
                    ->where('section_key', $meta['key'])
                    ->update([
                        'title' => $section['title'],
                        'content' => $section['content'],
                        'is_active' => true,
                        'updated_by' => 'system',
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('t_neo_knowledge')->insert([
                    'section_key' => $meta['key'],
                    'title' => $section['title'],
                    'content' => $section['content'],
                    'sort_order' => $meta['sort_order'],
                    'is_active' => true,
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        NeoKnowledgeRepository::forget();
    }

    public function down(): void
    {
        if (! Schema::hasTable('t_neo_knowledge')) {
            return;
        }

        DB::table('t_neo_knowledge')
            ->where('section_key', '13-panduan-navigasi-menu-portal-hris-karyawan-atasan')
            ->delete();

        NeoKnowledgeRepository::forget();
    }
};

