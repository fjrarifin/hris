<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineHeartbeatRemovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['mobile_device_tokens', 'personal_access_tokens', 'frontend_menu_user_access', 'frontend_menus', 'users', 'm_karyawan'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('photo')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('photo_changed_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->unsignedTinyInteger('level')->default(3);
            $table->boolean('is_active')->default(true);
            $table->boolean('allow_mobile_attendance')->default(false);
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik')->unique();
            $table->string('nama_karyawan');
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->date('join_date')->nullable();
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

        Schema::create('mobile_device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('token', 512);
            $table->string('platform', 30)->default('android');
            $table->string('device_name', 120)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_online_users_and_heartbeat_endpoints_are_removed(): void
    {
        $user = User::query()->create([
            'username' => 'EMP001',
            'name' => 'Budi',
            'email' => 'budi@example.test',
            'password' => Hash::make('secret123'),
            'level' => 3,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $token = $user->createToken('hris-fe')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/online-users/heartbeat', [])
            ->assertNotFound();

        $this->withToken($token)
            ->getJson('/api/online-users')
            ->assertNotFound();
    }

    public function test_it_dashboard_counts_recent_active_tokens_as_online_without_heartbeat(): void
    {
        $itUser = User::query()->create([
            'username' => 'IT001',
            'name' => 'IT Admin',
            'email' => 'it@example.test',
            'password' => Hash::make('secret123'),
            'level' => 0,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $recentEmployee = User::query()->create([
            'username' => 'EMP001',
            'name' => 'Karyawan Aktif',
            'email' => 'emp1@example.test',
            'password' => Hash::make('secret123'),
            'level' => 3,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        $staleEmployee = User::query()->create([
            'username' => 'EMP002',
            'name' => 'Karyawan Idle',
            'email' => 'emp2@example.test',
            'password' => Hash::make('secret123'),
            'level' => 3,
            'must_change_password' => false,
            'is_active' => true,
        ]);

        // Karyawan aktif punya 2 sesi (web + mobile) baru-baru ini: dihitung 1 user unik
        $webToken = $recentEmployee->createToken('hris-fe')->accessToken;
        $mobileToken = $recentEmployee->createToken('hris-mobile')->accessToken;
        DB::table('personal_access_tokens')
            ->whereIn('id', [$webToken->id, $mobileToken->id])
            ->update(['last_used_at' => now()->subSeconds(30)]);

        // Karyawan idle terakhir aktif 5 menit lalu: tidak masuk online_now
        $staleToken = $staleEmployee->createToken('hris-fe')->accessToken;
        DB::table('personal_access_tokens')
            ->where('id', $staleToken->id)
            ->update([
                'last_used_at' => now()->subMinutes(5),
                'created_at' => now()->subMinutes(10),
            ]);

        $itToken = $itUser->createToken('hris-fe')->plainTextToken;

        // IT Admin sendiri + Karyawan Aktif = 2 user online dalam 2 menit terakhir
        $this->withToken($itToken)
            ->getJson('/api/it/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.online_now', 2)
            ->assertJsonPath('summary.active_sessions.total', 4);
    }

    public function test_login_records_last_seen_at_without_heartbeat(): void
    {
        $employee = User::query()->create([
            'username' => 'EMP010',
            'name' => 'Login Test',
            'email' => 'emp10@example.test',
            'password' => Hash::make('secret123'),
            'level' => 3,
            'must_change_password' => false,
            'is_active' => true,
            'last_seen_at' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'username' => 'EMP010',
            'password' => 'secret123',
            'client' => 'web',
        ])->assertOk();

        $this->assertNotNull($employee->fresh()->last_seen_at);
    }
}
