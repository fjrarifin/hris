<?php

namespace Tests\Feature\Api;

use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SingleDeviceLoginApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['personal_access_tokens', 'frontend_menu_user_access', 'frontend_menus', 'users', 'm_karyawan'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('photo')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->unsignedTinyInteger('level')->default(3);
            $table->timestamps();
        });

        Schema::create('frontend_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('path');
            $table->string('icon')->nullable();
            $table->string('allowed_levels')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('frontend_menu_user_access', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('frontend_menu_id');
            $table->foreignId('user_id');
            $table->boolean('is_allowed');
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
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

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('email')->nullable();
            $table->timestamps();
        });

        FrontendMenu::query()->create([
            'key' => 'dashboard',
            'label' => 'Dashboard',
            'path' => '/dashboard',
            'allowed_levels' => '3',
            'is_active' => true,
        ]);

        User::query()->create([
            'username' => 'EMP001',
            'name' => 'Satu Perangkat',
            'email' => 'employee@example.test',
            'password' => Hash::make('password'),
            'level' => 3,
            'must_change_password' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_active_session_blocks_a_second_device_until_logout(): void
    {
        $firstLogin = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/137.0 Safari/537.36')
            ->postJson('/api/auth/login', [
                'username' => 'EMP001',
                'password' => 'password',
            ])
            ->assertOk()
            ->json();

        $this->assertDatabaseHas('personal_access_tokens', [
            'device_name' => 'Chrome di Windows',
            'ip_address' => '10.20.30.40',
        ]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
            ->postJson('/api/auth/login', [
                'username' => 'EMP001',
                'password' => 'password',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'ACTIVE_SESSION_EXISTS')
            ->assertJsonPath('active_session.device_name', 'Chrome di Windows')
            ->assertJsonPath('active_session.network_address', '10.20.x.x');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($firstLogin['token'])->postJson('/api/auth/logout')->assertOk();

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5) AppleWebKit/605.1.15 Version/18.0 Mobile/15E148 Safari/604.1')
            ->postJson('/api/auth/login', [
                'username' => 'EMP001',
                'password' => 'password',
            ])
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'device_name' => 'Safari di iPhone/iPad',
        ]);
    }

    public function test_employee_without_user_can_login_with_default_password_and_must_change_it(): void
    {
        Karyawan::query()->create([
            'nik' => 'EMP002',
            'nama_karyawan' => 'Login Pertama',
            'email' => 'first.login@example.test',
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'EMP002',
            'password' => 'salah',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['username' => 'EMP002']);

        $login = $this->postJson('/api/auth/login', [
            'username' => 'EMP002',
            'password' => '12345678',
        ])
            ->assertOk()
            ->assertJsonPath('user.must_change_password', true)
            ->json();

        $this->assertDatabaseHas('users', [
            'username' => 'EMP002',
            'level' => 3,
            'must_change_password' => true,
        ]);

        $this->withToken($login['token'])
            ->postJson('/api/auth/change-password', [
                'current_password' => '12345678',
                'password' => 'PasswordBaru123',
                'password_confirmation' => 'PasswordBaru123',
            ])
            ->assertOk()
            ->assertJsonPath('user.must_change_password', false);
    }

    public function test_idle_session_is_removed_and_allows_login_on_another_device_after_seven_days(): void
    {
        Carbon::setTestNow('2026-05-27 08:00:00');

        $this->postJson('/api/auth/login', [
            'username' => 'EMP001',
            'password' => 'password',
        ])->assertOk();

        Carbon::setTestNow('2026-06-03 08:01:00');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Version/18.0 Mobile Safari/604.1')
            ->postJson('/api/auth/login', [
                'username' => 'EMP001',
                'password' => 'password',
            ])
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', [
            'device_name' => 'Safari di iPhone/iPad',
        ]);
    }
}
