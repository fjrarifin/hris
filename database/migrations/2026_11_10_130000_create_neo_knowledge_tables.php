<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('t_neo_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 150)->unique();
            $table->string('title', 255);
            $table->longText('content')->comment('Markdown, termasuk baris heading seksi');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('t_neo_knowledge_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('neo_knowledge_id')->index();
            $table->string('title', 255);
            $table->longText('content');
            $table->string('changed_by', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        if (Schema::hasTable('t_whatsapp_bot_faq')) {
            Schema::table('t_whatsapp_bot_faq', function (Blueprint $table) {
                $table->integer('priority')->default(0)->after('answer')
                    ->comment('Semakin besar semakin diprioritaskan');
                $table->unsignedTinyInteger('max_user_level')->default(3)->after('priority')
                    ->comment('Level user tertinggi (angka) yang boleh menerima jawaban: 3=semua, 2=HRD ke atas, 0=IT saja');
                $table->string('match_type', 20)->default('contains')->after('max_user_level')
                    ->comment('contains | exact');
                $table->string('updated_by', 100)->nullable()->after('is_active');
            });
        }

        $this->seedFromManualFile();
        $this->registerMenu();
    }

    public function down(): void
    {
        if (Schema::hasTable('frontend_menus')) {
            DB::table('frontend_menus')->where('key', 'it-neo-knowledge')->delete();
        }

        if (Schema::hasTable('t_whatsapp_bot_faq')) {
            Schema::table('t_whatsapp_bot_faq', function (Blueprint $table) {
                $table->dropColumn(['priority', 'max_user_level', 'match_type', 'updated_by']);
            });
        }

        Schema::dropIfExists('t_neo_knowledge_revisions');
        Schema::dropIfExists('t_neo_knowledge');
    }

    /**
     * Impor sekali dari resources/knowledge/hris_employee_manual.md, satu baris per seksi "## ...".
     */
    private function seedFromManualFile(): void
    {
        $path = resource_path('knowledge/hris_employee_manual.md');
        if (! file_exists($path)) {
            return;
        }

        $raw = str_replace("\r\n", "\n", (string) file_get_contents($path));
        $chunks = preg_split('/^(?=## )/m', $raw) ?: [];
        $now = now();
        $used = [];

        foreach ($chunks as $index => $chunk) {
            $content = trim((string) preg_replace('/\n*-{3,}\s*$/', '', trim($chunk)));
            if ($content === '') {
                continue;
            }

            if ($index === 0) {
                $title = 'Pengantar';
            } else {
                $title = trim((string) preg_replace('/^##\s*/', '', strtok($content, "\n")));
            }

            $key = Str::slug($title) ?: 'seksi-' . $index;
            $key = Str::limit($key, 140, '');
            while (in_array($key, $used, true)) {
                $key .= '-' . $index;
            }
            $used[] = $key;

            DB::table('t_neo_knowledge')->insert([
                'section_key' => $key,
                'title' => $title,
                'content' => $content,
                'sort_order' => $index * 10,
                'is_active' => true,
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function registerMenu(): void
    {
        if (! Schema::hasTable('frontend_menus')) {
            return;
        }

        $now = now();
        $payload = [
            'label' => 'Neo Knowledge',
            'path' => '/it/neo-knowledge',
            'icon' => 'i-lucide-brain-circuit',
            'allowed_levels' => '0,2',
            'sort_order' => 50,
            'is_active' => true,
            'updated_at' => $now,
        ];

        if (DB::table('frontend_menus')->where('key', 'it-neo-knowledge')->exists()) {
            DB::table('frontend_menus')->where('key', 'it-neo-knowledge')->update($payload);
        } else {
            DB::table('frontend_menus')->insert($payload + ['key' => 'it-neo-knowledge', 'created_at' => $now]);
        }
    }
};

