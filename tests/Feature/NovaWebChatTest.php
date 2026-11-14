<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NovaWebChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.hris_agent.enabled', true);
        config()->set('services.hris_agent.persona_name', 'Nova');
        config()->set('services.ninerouter.base_url', 'http://ninerouter.test/v1');
        config()->set('services.ninerouter.api_key', 'test-key');

        foreach ([
            'gate_qr_usage_logs',
            'notifications',
            'personal_access_tokens',
            'm_karyawan',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->unsignedTinyInteger('level')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
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
            $table->string('atasan_tidak_langsung_nik')->nullable();
            $table->string('pin')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gate_qr_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nik');
            $table->string('employee_name')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_guest_cannot_access_nova_web_chat_endpoints(): void
    {
        $this->getJson('/api/nova/chat')->assertUnauthorized();
        $this->postJson('/api/nova/chat', ['message' => 'halo'])->assertUnauthorized();
        $this->deleteJson('/api/nova/chat')->assertUnauthorized();
    }

    public function test_authenticated_users_can_chat_fetch_history_and_clear_history_regardless_of_phone(): void
    {
        Http::fake();

        Karyawan::query()->create([
            'nik' => 'EMP100',
            'nama_karyawan' => 'Rina Kusuma',
            'no_hp' => null, // Sengaja kosong untuk membuktikan web chat tidak bergantung nomor WA
            'jabatan' => 'Staff',
            'status_karyawan' => 'Tetap',
        ]);

        $staffUser = User::query()->create([
            'username' => 'EMP100',
            'name' => 'Rina Kusuma',
            'email' => 'rina@example.test',
            'password' => Hash::make('Password123'),
            'level' => 3,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        RateLimiter::clear('web_ai_agent_limit:' . $staffUser->id);
        Sanctum::actingAs($staffUser);

        $chatRes = $this->postJson('/api/nova/chat', ['message' => 'kamu siapa?']);
        $chatRes->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('persona', 'Nova');
        $this->assertStringContainsString('Halo Kak Rina', (string) $chatRes->json('reply'));

        $this->getJson('/api/nova/chat')
            ->assertOk()
            ->assertJsonCount(2, 'history');

        $this->deleteJson('/api/nova/chat')
            ->assertOk()
            ->assertJsonPath('status', 'cleared')
            ->assertJsonCount(0, 'history');

        // User level 0, 1, dan 2 harus ditolak (403) karena chat web Nova khusus level 3 saja
        foreach ([0, 1, 2] as $restrictedLevel) {
            $nonStaff = User::query()->create([
                'username' => 'NONSTAFF' . $restrictedLevel,
                'name' => 'Non Staff ' . $restrictedLevel,
                'email' => "nonstaff{$restrictedLevel}@example.test",
                'password' => Hash::make('Password123'),
                'level' => $restrictedLevel,
                'is_active' => true,
                'must_change_password' => false,
            ]);

            Sanctum::actingAs($nonStaff);

            $this->getJson('/api/nova/chat')->assertForbidden();
            $this->postJson('/api/nova/chat', ['message' => 'kamu siapa?'])->assertForbidden();
            $this->deleteJson('/api/nova/chat')->assertForbidden();
        }

        Http::assertNothingSent();
    }

    public function test_web_reset_session_revokes_mobile_and_other_web_tokens_but_preserves_current_web_session(): void
    {
        Http::fake();

        Karyawan::query()->create([
            'nik' => 'EMP200',
            'nama_karyawan' => 'Dodi Pratama',
            'no_hp' => '081299998888',
            'status_karyawan' => 'Tetap',
        ]);

        $user = User::query()->create([
            'username' => 'EMP200',
            'name' => 'Dodi Pratama',
            'email' => 'dodi@example.test',
            'password' => Hash::make('Password123'),
            'level' => 3,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $currentWebToken = $user->createToken('hris-fe');
        $otherWebToken = $user->createToken('hris-fe');
        $mobileToken = $user->createToken('hris-mobile');

        RateLimiter::clear('web_ai_agent_limit:' . $user->id);

        $res = $this->withToken($currentWebToken->plainTextToken)
            ->postJson('/api/nova/chat', ['message' => 'reset session login']);

        $res->assertOk();
        $this->assertStringContainsString('Sesi web yang sedang kamu gunakan saat ini tetap aktif', (string) $res->json('reply'));

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentWebToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherWebToken->accessToken->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $mobileToken->accessToken->id]);

        // Cek password request di Web Chat tidak me-reset password & tidak me-logout session
        $originalHash = $user->fresh()->password;
        $pwRes = $this->withToken($currentWebToken->plainTextToken)
            ->postJson('/api/nova/chat', ['message' => 'reset password']);

        $pwRes->assertOk();
        $this->assertStringContainsString('Profil', (string) $pwRes->json('reply'));
        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentWebToken->accessToken->id]);

        Http::assertNothingSent();
    }

    public function test_web_chat_can_generate_qr_gate_image_and_level3_without_nik_cannot_run_sql(): void
    {
        Http::fake();

        Karyawan::query()->create([
            'nik' => 'EMP300',
            'nama_karyawan' => 'Sari Wulandari',
            'status_karyawan' => 'Tetap',
        ]);

        $user = User::query()->create([
            'username' => 'EMP300',
            'name' => 'Sari Wulandari',
            'email' => 'sari@example.test',
            'password' => Hash::make('Password123'),
            'level' => 3,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        RateLimiter::clear('web_ai_agent_limit:' . $user->id);
        Sanctum::actingAs($user);

        $qrRes = $this->postJson('/api/nova/chat', ['message' => 'minta qr gate kartu tertinggal']);
        $qrRes->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertStringContainsString('QR Code Gate Turnstile Berhasil Dibuat', (string) $qrRes->json('reply'));
        $this->assertStringStartsWith('https://api.qrserver.com/v1/create-qr-code/', (string) $qrRes->json('image_url'));
        $this->assertDatabaseHas('gate_qr_usage_logs', ['nik' => 'EMP300']);

        // Pastikan level 3 tanpa NIK karyawan ditolak oleh validateSqlSafety
        $dbAgent = app(\App\Services\HrisDatabaseQueryAgent::class);
        $refl = new \ReflectionClass($dbAgent);
        $val = $refl->getMethod('validateSqlSafety');
        $val->setAccessible(true);

        $check = $val->invoke($dbAgent, 'SELECT COUNT(*) FROM m_karyawan', '', 3, '');
        $this->assertFalse($check['safe']);
    }
}

