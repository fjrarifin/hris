<?php

namespace Tests\Feature;

use App\Models\NeoKnowledge;
use App\Services\HrisDatabaseQueryAgent;
use App\Services\NeoKnowledgeRepository;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NeoSingleSourceOfTruthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.hris_agent.bot_url', '');
        config()->set('services.hris_agent.enabled', true);
        config()->set('services.hris_agent.trigger_prefix', '');
        config()->set('services.hris_agent.webhook_token', '');
        config()->set('services.hris_agent.allowed_senders', []);
        config()->set('services.hris_agent.admin_phones', '628123456789');
        config()->set('services.ninerouter.base_url', 'http://ninerouter.test/v1');
        config()->set('services.ninerouter.api_key', 'test-key');

        Schema::dropIfExists('t_neo_knowledge');
        Schema::create('t_neo_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('section_key', 150)->unique();
            $table->string('title', 255);
            $table->longText('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();
        });

        NeoKnowledgeRepository::forget();
    }

    public function test_neo_uses_t_neo_knowledge_as_single_source_of_truth_without_hardcoded_sop(): void
    {
        NeoKnowledge::create([
            'section_key' => 'aturan-ph-dinamis',
            'title' => 'Aturan PH Dinamis',
            'content' => 'Masa berlaku PH uji coba adalah 120 hari kalender.',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        NeoKnowledgeRepository::forget();

        $dbAgent = app(HrisDatabaseQueryAgent::class);
        $rulesContext = $dbAgent->getBusinessRulesContext();

        $this->assertStringContainsString('Masa berlaku PH uji coba adalah 120 hari kalender.', $rulesContext);
        $this->assertStringNotContainsString('=== SOP & ALUR BISNIS HRIS HOMPIM PLAY ===', $rulesContext);
        $this->assertStringNotContainsString('berlaku selama 90 hari', $rulesContext);

        Http::fake([
            'http://whatsapp.test/*' => Http::response(['ok' => true]),
            'http://ninerouter.test/*' => function ($request) {
                $systemMsg = (string) ($request->data()['messages'][0]['content'] ?? '');
                if (str_contains($systemMsg, 'Database Text-to-SQL Engine')) {
                    return Http::response([
                        'choices' => [['message' => ['content' => 'NONE']]],
                    ]);
                }

                return Http::response([
                    'choices' => [['message' => ['content' => 'Masa berlaku PH sesuai aturan terbaru adalah 120 hari.']]],
                ]);
            },
        ]);

        $response = $this->postJson('/api/whatsapp/ai-agent/webhook', [
            'phone' => '628123456789',
            'message' => 'Berapa lama masa berlaku PH saat ini?',
        ]);

        $response->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSent(function ($request): bool {
            if (! str_starts_with($request->url(), 'http://ninerouter.test/')) {
                return false;
            }

            $messages = $request->data()['messages'] ?? [];
            $systemMsg = (string) ($messages[0]['content'] ?? '');
            $lastUserMsg = (string) (collect($messages)->last()['content'] ?? '');

            return str_contains($systemMsg, '[BLOK 1 - IDENTITAS & PERAN]')
                && str_contains($systemMsg, '[BLOK 8 - KEAMANAN & PERLINDUNGAN RAHASIA]')
                && str_contains($systemMsg, '<panduan_sop_resmi>')
                && str_contains($systemMsg, 'Masa berlaku PH uji coba adalah 120 hari kalender.')
                && ! str_contains($systemMsg, 'KNOWLEDGE BASE & LOGIKA BISNIS HRIS HOMPIM PLAY (SOP RESMI)')
                && ! str_contains($systemMsg, 'Password default portal HRIS adalah 12345678')
                && str_contains($lastUserMsg, '<pertanyaan_pengguna>');
        });
    }

    public function test_updating_t_neo_knowledge_immediately_updates_both_db_agent_and_chat_prompt(): void
    {
        $row = NeoKnowledge::create([
            'section_key' => 'aturan-ph-dinamis',
            'title' => 'Aturan PH Dinamis',
            'content' => 'Masa berlaku PH awal adalah 120 hari.',
            'sort_order' => 10,
            'is_active' => true,
        ]);
        NeoKnowledgeRepository::forget();

        $dbAgent = app(HrisDatabaseQueryAgent::class);
        $this->assertStringContainsString('120 hari', $dbAgent->getBusinessRulesContext());

        $row->update(['content' => 'Aturan PH diperbarui menjadi 45 hari.']);
        NeoKnowledgeRepository::forget();

        $updated = $dbAgent->getBusinessRulesContext();
        $this->assertStringContainsString('Aturan PH diperbarui menjadi 45 hari.', $updated);
        $this->assertStringNotContainsString('120 hari', $updated);

        // Uji juga jalur fallback HrisWhatsAppAgent::buildSystemPrompt langsung memakai data t_neo_knowledge terbaru
        $this->mock(HrisDatabaseQueryAgent::class, function ($mock): void {
            $mock->shouldReceive('queryAndAnswer')->once()->andReturn(null);
        });

        Http::fake([
            'http://whatsapp.test/*' => Http::response(['ok' => true]),
            'http://ninerouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'Aturan PH sekarang berlaku 45 hari.']]],
            ]),
        ]);

        $res = $this->postJson('/api/whatsapp/ai-agent/webhook', [
            'phone' => '628123456789',
            'message' => 'Jelaskan aturan PH terbaru dong',
        ]);
        $res->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSent(function ($request): bool {
            if (! str_starts_with($request->url(), 'http://ninerouter.test/')) {
                return false;
            }

            $systemMsg = (string) ($request->data()['messages'][0]['content'] ?? '');

            return str_contains($systemMsg, 'Aturan PH diperbarui menjadi 45 hari.')
                && ! str_contains($systemMsg, '120 hari')
                && ! str_contains($systemMsg, 'KNOWLEDGE BASE & LOGIKA BISNIS HRIS HOMPIM PLAY (SOP RESMI)');
        });
    }

    public function test_identity_question_is_answered_locally_without_calling_llm(): void
    {
        Http::fake([
            'http://whatsapp.test/*' => Http::response(['ok' => true]),
        ]);

        $response = $this->postJson('/api/whatsapp/ai-agent/webhook', [
            'phone' => '628123456789',
            'message' => 'kamu siapa?',
        ]);

        $response->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://whatsapp.test/send/message'
            && str_contains((string) data_get($request->data(), 'message'), 'Saya Nova, asisten AI resmi sistem HRIS'));

        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'http://ninerouter.test/'));
    }
}
