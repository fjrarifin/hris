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

    public function test_bot_url_outbound_sends_hris_agent_webhook_token_header(): void
    {
        config()->set('services.hris_agent.bot_url', 'http://nova-bot.test:3352');
        Http::fake([
            'http://nova-bot.test:3352/*' => Http::response(['success' => true]),
        ]);
        RateLimiter::clear('wa_ai_agent_limit:6281111111111');

        $response = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'sender_phone' => '6281111111111',
                'from' => '6281111111111@s.whatsapp.net',
                'message' => 'siapa nova?',
            ]);

        $response->assertOk()->assertJson(['status' => 'sent']);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://nova-bot.test:3352/send/message'
            && $request->hasHeader('X-HRIS-Agent-Token', 'nova-test-token'));
    }

    public function test_db_query_agent_schema_uses_real_columns_without_total_days(): void
    {
        $schema = app(\App\Services\HrisDatabaseQueryAgent::class)->getSchemaContext();

        $this->assertStringNotContainsString('total_days', $schema);
        $this->assertStringContainsString('DATEDIFF(end_date, start_date) + 1', $schema);
        $this->assertStringContainsString('employee_ph_balances', $schema);
        $this->assertStringContainsString('attendance_date', $schema);
        $this->assertStringContainsString('nama_jabatan', $schema);
        $this->assertStringContainsString('tanggal_mulai', $schema);
    }

    public function test_level_3_staff_can_query_own_fingerspot_by_pin_while_blocking_other_pin_or_tables(): void
    {
        Schema::dropIfExists('fingerspot_attendance_logs');
        Schema::create('fingerspot_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 50);
            $table->dateTime('scan_date');
            $table->string('status_scan', 20);
        });

        \Illuminate\Support\Facades\DB::table('fingerspot_attendance_logs')->insert([
            'pin' => '0147',
            'scan_date' => now()->format('Y-m-d') . ' 08:55:00',
            'status_scan' => '0',
        ]);

        $emp = Karyawan::where('nik', 'EMP001')->firstOrFail();
        $emp->forceFill(['pin' => '0147'])->save();

        Http::fake([
            'http://ninerouter.test/*' => function ($request) {
                $sys = (string) ($request->data()['messages'][0]['content'] ?? '');
                if (str_contains($sys, 'Database Text-to-SQL Engine')) {
                    return Http::response([
                        'choices' => [[
                            'message' => [
                                'content' => "SELECT scan_date, status_scan FROM fingerspot_attendance_logs WHERE pin = '0147' ORDER BY scan_date ASC",
                            ],
                        ]],
                    ]);
                }

                $this->assertStringContainsString('08:55:00', $sys);

                return Http::response([
                    'choices' => [[
                        'message' => [
                            'content' => 'Kak Budi sudah absen masuk pukul 08:55 hari ini.',
                        ],
                    ]],
                ]);
            },
        ]);

        $dbAgent = app(\App\Services\HrisDatabaseQueryAgent::class);
        $answer = $dbAgent->queryAndAnswer('saya hari ini udah absen belum?', $emp, [], 3);
        $this->assertSame('Kak Budi sudah absen masuk pukul 08:55 hari ini.', $answer);

        // Verify validateSqlSafety blocks other PIN or non-attendance table with only PIN filter
        $refl = new \ReflectionClass($dbAgent);
        $val = $refl->getMethod('validateSqlSafety');
        $val->setAccessible(true);

        $badPin = $val->invoke($dbAgent, "SELECT scan_date FROM fingerspot_attendance_logs WHERE pin = '9999'", 'EMP001', 3, '0147');
        $this->assertFalse($badPin['safe']);

        $badTable = $val->invoke($dbAgent, "SELECT reason FROM leave_requests WHERE pin = '0147'", 'EMP001', 3, '0147');
        $this->assertFalse($badTable['safe']);
    }

    public function test_when_sql_agent_returns_none_or_fails_fallback_preserves_employee_leave_balance_context(): void
    {
        Schema::dropIfExists('t_kontrak_karyawan');
        Schema::dropIfExists('leave_accruals');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_extra_offs');
        Schema::dropIfExists('extra_off_requests');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('public_holiday_requests');
        Schema::dropIfExists('employee_ph_adjustments');
        Schema::dropIfExists('fingerspot_attendance_logs');

        Schema::create('t_kontrak_karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nik');
            $table->string('status_kontrak')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
        });
        Schema::create('leave_accruals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nik')->nullable();
            $table->integer('year')->nullable();
            $table->integer('month')->nullable();
            $table->date('accrued_at')->nullable();
            $table->decimal('days', 4, 2)->default(1);
            $table->date('expired_at')->nullable();
            $table->boolean('is_used')->default(false);
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('leave_type')->default('cuti_tahunan');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('approved');
        });
        Schema::create('employee_extra_offs', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('periode_start');
            $table->date('periode_end');
            $table->integer('days')->default(0);
            $table->date('expires_at')->nullable();
            $table->unsignedBigInteger('public_holiday_request_id')->nullable();
            $table->timestamps();
        });
        Schema::create('extra_off_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('claim_date');
            $table->date('source_period_start')->nullable();
            $table->date('source_period_end')->nullable();
            $table->unsignedBigInteger('employee_extra_off_id')->nullable();
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('public_holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->boolean('is_active')->default(true);
        });
        Schema::create('public_holiday_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('public_holiday_id');
            $table->date('claim_date');
            $table->string('status')->default('approved');
            $table->timestamps();
        });
        Schema::create('employee_ph_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('karyawan_nik');
            $table->unsignedBigInteger('public_holiday_id')->nullable();
            $table->integer('days')->default(0);
        });
        Schema::create('fingerspot_attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('pin', 50);
            $table->dateTime('scan_date');
            $table->string('status_scan', 20);
        });

        $userId = \Illuminate\Support\Facades\DB::table('users')->insertGetId([
            'username' => 'EMP001',
            'name' => 'Budi Santoso',
            'phone' => '081111111111',
            'level' => 3,
            'is_active' => true,
        ]);
        \Illuminate\Support\Facades\DB::table('t_kontrak_karyawan')->insert([
            'nik' => 'EMP001',
            'status_kontrak' => 'AKTIF',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
        ]);
        \Illuminate\Support\Facades\DB::table('leave_accruals')->insert([
            ['user_id' => $userId, 'nik' => 'EMP001', 'year' => 2026, 'month' => 9, 'days' => 1, 'expired_at' => now()->addMonths(6)->toDateString(), 'is_used' => 0],
            ['user_id' => $userId, 'nik' => 'EMP001', 'year' => 2026, 'month' => 10, 'days' => 1, 'expired_at' => now()->addMonths(6)->toDateString(), 'is_used' => 0],
            ['user_id' => $userId, 'nik' => 'EMP001', 'year' => 2026, 'month' => 11, 'days' => 1, 'expired_at' => now()->addMonths(6)->toDateString(), 'is_used' => 0],
        ]);
        \Illuminate\Support\Facades\DB::table('leave_requests')->insert([
            'user_id' => $userId,
            'leave_type' => 'cuti_tahunan',
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
            'status' => 'approved',
        ]);

        Http::fake([
            'http://ninerouter.test/*' => function ($request) {
                $sys = (string) ($request->data()['messages'][0]['content'] ?? '');
                if (str_contains($sys, 'Database Text-to-SQL Engine')) {
                    return Http::response([
                        'choices' => [['message' => ['content' => 'NONE']]],
                    ]);
                }

                $this->assertStringContainsString('[Info Karyawan yang Bertanya]', $sys);
                $this->assertStringContainsString('Sisa Saldo Cuti Tahunan: 2 hari', $sys);
                $this->assertStringContainsString('Sisa Saldo PH (Public Holiday):', $sys);
                $this->assertStringContainsString('Sisa Saldo Extra Off Aktif:', $sys);

                return Http::response([
                    'choices' => [['message' => ['content' => 'Saldo cuti tahunan Kak Budi saat ini 2 hari.']]],
                ]);
            },
        ]);

        RateLimiter::clear('wa_ai_agent_limit:6281111111111');

        $response = $this->withHeaders(['X-HRIS-Agent-Token' => 'nova-test-token'])
            ->postJson('/api/whatsapp/ai-agent/webhook', [
                'sender_phone' => '6281111111111',
                'from' => '6281111111111@s.whatsapp.net',
                'message' => 'saldo cuti saya berapa?',
                'return_reply' => true,
            ]);

        $response->assertOk()->assertJson([
            'status' => 'sent',
            'reply' => 'Saldo cuti tahunan Kak Budi saat ini 2 hari.',
        ]);
    }
}
