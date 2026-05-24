<?php

namespace Tests\Feature\Api;

use App\Models\FingerspotAttendanceLog;
use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffAttendanceApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('frontend_menu_user_access');
        Schema::dropIfExists('frontend_menus');
        Schema::dropIfExists('fingerspot_attendance_logs');
        Schema::dropIfExists('m_karyawan');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('level')->default(3);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('pin')->nullable();
            $table->string('nik')->unique();
            $table->string('nama_karyawan');
            $table->timestamps();
        });

        Schema::create('fingerspot_attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('pin');
            $table->dateTime('scan_date');
            $table->string('status_scan')->nullable();
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

        FrontendMenu::query()->create([
            'key' => 'staff-attendance',
            'label' => 'Absensi Saya',
            'path' => '/staff/attendance',
            'allowed_levels' => '3',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'username' => 'HPP25120147',
            'name' => 'Budi Karyawan',
            'email' => 'budi@example.test',
            'password' => 'password',
            'level' => 3,
            'must_change_password' => false,
        ]);

        Karyawan::query()->create([
            'nik' => $user->username,
            'pin' => 'PIN-001',
            'nama_karyawan' => $user->name,
        ]);

        Sanctum::actingAs($user);
    }

    public function test_employee_can_view_daily_attendance_using_first_and_last_scan(): void
    {
        $this->createLog('PIN-001', '2026-05-20 08:11:00');
        $this->createLog('PIN-001', '2026-05-20 07:55:00');
        $this->createLog('PIN-001', '2026-05-20 17:13:00');
        $this->createLog('PIN-001', '2026-05-21 08:02:00');
        $this->createLog('PIN-OTHER', '2026-05-20 06:00:00');

        $this->getJson('/api/staff/attendance?start_date=2026-05-20&end_date=2026-05-21')
            ->assertOk()
            ->assertJsonPath('summary.attendance_days', 2)
            ->assertJsonPath('summary.complete_days', 1)
            ->assertJsonPath('summary.incomplete_days', 1)
            ->assertJsonCount(2, 'records')
            ->assertJsonPath('records.0.date', '2026-05-21')
            ->assertJsonPath('records.0.scan_in', '08:02:00')
            ->assertJsonPath('records.0.scan_out', null)
            ->assertJsonPath('records.1.date', '2026-05-20')
            ->assertJsonPath('records.1.scan_in', '07:55:00')
            ->assertJsonPath('records.1.scan_out', '17:13:00')
            ->assertJsonPath('records.1.total_scans', 3);
    }

    public function test_single_supplied_date_filters_only_that_day(): void
    {
        $this->createLog('PIN-001', '2026-05-18 08:00:00');
        $this->createLog('PIN-001', '2026-05-20 08:00:00');
        $this->createLog('PIN-001', '2026-05-20 17:00:00');

        $this->getJson('/api/staff/attendance?start_date=2026-05-20')
            ->assertOk()
            ->assertJsonPath('filters.start_date', '2026-05-20')
            ->assertJsonPath('filters.end_date', '2026-05-20')
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.date', '2026-05-20');
    }

    private function createLog(string $pin, string $scanDate): void
    {
        FingerspotAttendanceLog::query()->create([
            'pin' => $pin,
            'scan_date' => $scanDate,
        ]);
    }
}
