<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppAiAgentWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.hris_agent.enabled', true);
        config()->set('services.hris_agent.trigger_prefix', '');
        config()->set('services.hris_agent.webhook_token', '');
        config()->set('services.hris_agent.allowed_senders', []);
        config()->set('services.hris_agent.created_date', '2026-05-28');
    }

    public function test_it_answers_basic_hris_question_from_whatsapp_webhook(): void
    {
        Http::fake([
            'http://whatsapp.test/send/message' => Http::response(['ok' => true]),
        ]);

        $response = $this->postJson('/api/whatsapp/ai-agent/webhook', [
            'phone' => '082219449223',
            'message' => 'Apa itu HRIS?',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'sent']);

        Http::assertSent(fn ($request): bool => $request['phone'] === '6282219449223'
            && str_contains($request['message'], 'Human Resource Information System'));
    }

    public function test_it_answers_created_date_from_nested_gowa_payload(): void
    {
        Http::fake([
            'http://whatsapp.test/send/message' => Http::response(['ok' => true]),
        ]);

        $response = $this->postJson('/api/whatsapp/ai-agent/webhook', [
            'data' => [
                'key' => [
                    'remoteJid' => '628123456789@s.whatsapp.net',
                    'fromMe' => false,
                ],
                'message' => [
                    'conversation' => 'Kapan aplikasi ini pertama kali dibuat?',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'sent']);

        Http::assertSent(fn ($request): bool => $request['phone'] === '628123456789'
            && str_contains($request['message'], '2026-05-28'));
    }

    public function test_it_rejects_webhook_when_token_is_invalid(): void
    {
        config()->set('services.hris_agent.webhook_token', 'secret-token');
        Http::fake();

        $response = $this->postJson('/api/whatsapp/ai-agent/webhook', [
            'phone' => '082219449223',
            'message' => 'Apa itu HRIS?',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'status' => 'rejected',
                'reason' => 'invalid_token',
            ]);

        Http::assertNothingSent();
    }
}
