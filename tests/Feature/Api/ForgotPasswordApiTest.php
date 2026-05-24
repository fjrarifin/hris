<?php

namespace Tests\Feature\Api;

use App\Http\Services\WhatsAppService;
use App\Models\Karyawan;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class ForgotPasswordApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('m_karyawan');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('must_change_password')->default(false);
            $table->unsignedTinyInteger('level')->default(3);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik')->unique();
            $table->string('nama_karyawan');
            $table->string('no_hp')->nullable();
            $table->timestamps();
        });

        Schema::create('password_reset_otps', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->string('otp', 6);
            $table->timestamp('expired_at');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_user_can_request_six_digit_otp_valid_for_two_minutes(): void
    {
        $user = $this->employeeUser();

        $this->mock(WhatsAppService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with('081234567890', \Mockery::on(fn (string $message) => str_contains($message, '2 menit')))
                ->andReturnTrue();
        });

        $this->postJson('/api/auth/forgot-password/request-otp', ['nik' => $user->username])
            ->assertOk()
            ->assertJsonPath('expires_in', 120);

        $otp = PasswordResetOtp::query()->firstOrFail();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp->otp);
        $this->assertTrue($otp->expired_at->between(now()->addSeconds(115), now()->addSeconds(125)));
    }

    public function test_verified_otp_can_reset_password_and_revoke_existing_tokens(): void
    {
        $user = $this->employeeUser();
        $user->createToken('portal-session');
        $otp = PasswordResetOtp::query()->create([
            'email' => $user->email,
            'otp' => '104725',
            'expired_at' => now()->addMinutes(2),
        ]);

        $this->postJson('/api/auth/forgot-password/verify-otp', [
            'nik' => $user->username,
            'otp' => '104725',
        ])->assertOk();

        $this->postJson('/api/auth/forgot-password/reset', [
            'nik' => $user->username,
            'otp' => '104725',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'password kamu sudah diganti, silahkan login menggunakan password terbaru'
            );

        $this->assertTrue(Hash::check('Password123', $user->fresh()->password));
        $this->assertTrue($otp->fresh()->is_used);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/auth/forgot-password/reset', [
            'nik' => $user->username,
            'otp' => '104725',
            'password' => 'Password456',
            'password_confirmation' => 'Password456',
        ])->assertUnprocessable();
    }

    public function test_reset_password_requires_letters_and_numbers(): void
    {
        $user = $this->employeeUser();

        PasswordResetOtp::query()->create([
            'email' => $user->email,
            'otp' => '987654',
            'expired_at' => now()->addMinutes(2),
        ]);

        $this->postJson('/api/auth/forgot-password/reset', [
            'nik' => $user->username,
            'otp' => '987654',
            'password' => 'abcdefgh',
            'password_confirmation' => 'abcdefgh',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    private function employeeUser(): User
    {
        $user = User::query()->create([
            'username' => 'HPP25120147',
            'name' => 'Budi Karyawan',
            'email' => 'budi@example.test',
            'password' => Hash::make('password'),
            'must_change_password' => false,
        ]);

        Karyawan::query()->create([
            'nik' => $user->username,
            'nama_karyawan' => $user->name,
            'no_hp' => '081234567890',
        ]);

        return $user;
    }
}
