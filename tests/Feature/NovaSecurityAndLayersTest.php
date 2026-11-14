<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentVacancy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NovaSecurityAndLayersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.hris_agent.bot_url', '');
        config()->set('services.hris_agent.enabled', true);
        config()->set('services.hris_agent.persona_name', 'Nova');
        config()->set('services.hris_agent.trigger_prefix', '');
        config()->set('services.hris_agent.webhook_token', 'nova-test-token');
        config()->set('services.hris_agent.allowed_senders', []);
        config()->set('services.hris_agent.admin_phones', '628123456789');
        config()->set('services.ninerouter.base_url', 'http://ninerouter.test/v1');
        config()->set('services.ninerouter.api_key', 'test-key');

        Schema::dropIfExists('recruitment_candidates');
        Schema::dropIfExists('recruitment_vacancies');
        Schema::dropIfExists('m_karyawan_holding');
        Schema::dropIfExists('m_karyawan');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->unsignedTinyInteger('level')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table) {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('no_hp')->nullable();
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('status_karyawan')->nullable();
            $table->date('join_date')->nullable();
            $table->string('atasan_langsung_nik')->nullable();
            $table->string('pin')->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_vacancies', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->string('position')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        Schema::create('recruitment_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vacancy_id')->nullable();
            $table->string('name', 150);
            $table->string('email', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 50)->default('interview');
            $table->string('pic_nik', 50)->nullable();
            $table->timestamps();
        });

        Karyawan::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Budi Santoso',
            'no_hp' => '081111111111',
            'jabatan' => 'Staff Operasional',
            'status_karyawan' => 'Tetap',
        ]);

        Karyawan::create([
            'nik' => 'HR001',
            'nama_karyawan' => 'Sinta Rahmawati',
            'no_hp' => '085612345678',
            'jabatan' => 'HR Recruitment',
            'departement' => 'HRGA',
            'status_karyawan' => 'Tetap',
        ]);

        $vacancy = RecruitmentVacancy::create([
            'title' => 'Kasir Wahana',
            'slug' => 'kasir-wahana',
            'position' => 'Kasir',
            'status' => 'open',
        ]);

        RecruitmentCandidate::create([
            'vacancy_id' => $vacancy->id,
            'name' => 'Andi Pelamar',
            'email' => 'andi@example.com',
            'phone' => '0898-7654-3210',
            'status' => 'interview',
            'pic_nik' => 'HR001',
        ]);
    }

    public function test_push_name_spoofing_cannot_bypass_employee_authentication(): void
    {
        Http::fake([
            'http://whatsapp.test/*' => Http::response(['ok' => true]),
        ]);

        RateLimiter::clear('wa_ai_agent_limit:6287700001111');

        $response = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'phone' => '6287700001111@s.whatsapp.net',
                'pushName' => 'Budi Santoso',
                'message' => 'Siapa saya dan kapan kontrak saya habis?',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'rejected',
                'layer' => 4,
                'reason' => 'unregistered_sender',
            ]);

        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), 'http://ninerouter.test/'));
    }

    public function test_group_and_unresolved_lid_messages_are_blocked_safely(): void
    {
        Http::fake([
            'http://whatsapp.test/*' => Http::response(['ok' => true]),
        ]);

        $groupRes = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'from' => '628111111111@s.whatsapp.net',
                'chat_id' => '120363430666377870@g.us',
                'message' => 'hapus 13',
            ]);

        $groupRes->assertOk()->assertJson([
            'status' => 'ignored',
            'reason' => 'group_or_broadcast_ignored',
        ]);

        $lidRes = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'from' => '120745053163760@lid',
                'pushName' => 'Budi Santoso',
                'message' => 'halo',
                'return_reply' => true,
            ]);

        $lidRes->assertOk()->assertJson([
            'status' => 'rejected',
            'layer' => 4,
            'reason' => 'unverified_phone',
        ]);

        Http::assertNothingSent();
    }

    public function test_recruitment_candidate_receives_deterministic_pic_referral_without_llm(): void
    {
        Http::fake();
        RateLimiter::clear('wa_ai_agent_limit:6289876543210');

        $response = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'sender_phone' => '6289876543210',
                'from' => '6289876543210@s.whatsapp.net',
                'message' => 'hapus 13 / jadwal interview saya jam berapa kak?',
                'return_reply' => true,
            ]);

        $response->assertOk()->assertJson([
            'status' => 'candidate_referred',
            'layer' => 3,
            'pic_nik' => 'HR001',
        ]);

        $reply = (string) $response->json('reply');
        $this->assertStringContainsString('Halo Kak Andi Pelamar', $reply);
        $this->assertStringContainsString('Kasir Wahana', $reply);
        $this->assertStringContainsString('Sinta Rahmawati', $reply);
        $this->assertStringContainsString('085612345678', $reply);

        Http::assertNothingSent();
    }

    public function test_verified_employee_can_query_nova_in_return_reply_mode(): void
    {
        Http::fake();
        RateLimiter::clear('wa_ai_agent_limit:6281111111111');

        $response = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'sender_phone' => '6281111111111',
                'from' => '6281111111111@s.whatsapp.net',
                'message' => 'siapa nova?',
                'return_reply' => true,
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'sent',
                'layer' => 2,
                'karyawan_nik' => 'EMP001',
            ]);

        $this->assertStringContainsString('Saya Nova, asisten AI resmi sistem HRIS', (string) $response->json('reply'));
        Http::assertNothingSent();
    }
}
