<?php

namespace Tests\Feature\Api;

use App\Exports\HrAttendanceExport;
use App\Http\Controllers\Api\HrAttendanceController;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrAttendancePivotApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-26 12:00:00');

        foreach ([
            'public_holiday_requests',
            'employee_permissions',
            'public_holidays',
            'leave_requests',
            'fingerspot_attendance_logs',
            'm_karyawan',
            'frontend_menu_user_access',
            'frontend_menus',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $this->createTables();

        FrontendMenu::query()->create([
            'key' => 'attendance',
            'label' => 'Absensi',
            'path' => '/attendance',
            'allowed_levels' => '2',
            'is_active' => true,
        ]);

        $hr = $this->createUser('HR001', 'HRD', 2);
        Sanctum::actingAs($hr);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_pivots_attendance_per_employee_and_calculates_period_totals(): void
    {
        $staff = $this->employee('EMP001', 'Ayu', 'PIN-A', '2026-01-01');
        $this->employee('EMP002', 'Budi', null, '2026-05-26');

        PublicHoliday::query()->create([
            'name' => 'Hari Libur Uji',
            'holiday_date' => '2026-05-25',
            'year' => 2026,
            'is_active' => true,
        ]);

        $this->log('PIN-A', '2026-05-25 08:00:00', '0');
        $this->log('PIN-A', '2026-05-25 17:30:35', '1');

        PublicHolidayRequest::query()->create([
            'user_id' => $staff->id,
            'claim_date' => '2026-05-24',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        LeaveRequest::query()->create([
            'user_id' => $staff->id,
            'leave_type' => 'cuti_tahunan',
            'start_date' => '2026-05-26',
            'end_date' => '2026-05-26',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        EmployeePermission::query()->create([
            'user_id' => $staff->id,
            'type' => 'sakit',
            'date' => '2026-05-27',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        EmployeePermission::query()->create([
            'user_id' => $staff->id,
            'type' => 'izin',
            'date' => '2026-05-28',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        $response = $this->getJson('/api/hr/attendance?start_date=2026-05-24&end_date=2026-05-28');

        $response
            ->assertOk()
            ->assertJsonPath('dates.0', '2026-05-24')
            ->assertJsonPath('dates.4', '2026-05-28')
            ->assertJsonPath('records.0.nik', 'EMP001')
            ->assertJsonPath('records.0.days.2026-05-24.status', 'PH')
            ->assertJsonPath('records.0.days.2026-05-25.status', 'M')
            ->assertJsonPath('records.0.days.2026-05-26.status', 'Cuti')
            ->assertJsonPath('records.0.days.2026-05-27.status', 'S')
            ->assertJsonPath('records.0.days.2026-05-28.status', 'I')
            ->assertJsonPath('records.0.total_attendance', 3)
            ->assertJsonPath('records.0.total_work_duration_minutes', 570)
            ->assertJsonPath('records.0.total_ph', 1)
            ->assertJsonPath('records.0.total_leave', 1)
            ->assertJsonPath('records.0.total_sick', 1)
            ->assertJsonPath('records.0.total_permission', 1)
            ->assertJsonPath('records.0.total_national_holiday_attendance', 1)
            ->assertJsonPath('records.1.days.2026-05-26.status', 'A')
            ->assertJsonPath('records.1.total_alpha', 5)
            ->assertJsonPath('summary.total_attendance', 3)
            ->assertJsonPath('summary.total_work_duration_minutes', 570)
            ->assertJsonPath('summary.national_holiday_attendance', 1);
    }

    public function test_export_headings_follow_the_pivot_dates(): void
    {
        $controller = app(HrAttendanceController::class);
        $reportMethod = (new \ReflectionClass($controller))->getMethod('report');
        $reportMethod->setAccessible(true);
        $report = $reportMethod->invoke($controller, [
            'start_date' => '2026-05-24',
            'end_date' => '2026-05-26',
        ]);

        $headings = (new HrAttendanceExport($report['records'], $report['dates']))->headings();

        $this->assertContains('24/05/2026', $headings);
        $this->assertContains('26/05/2026', $headings);
        $this->assertSame('Total M Hari Libur Nasional', end($headings));
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('level');
            $table->boolean('must_change_password')->default(false);
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

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('pin')->nullable();
            $table->string('nama_karyawan');
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('unit')->nullable();
            $table->date('join_date')->nullable();
            $table->timestamps();
        });

        Schema::create('fingerspot_attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('pin');
            $table->dateTime('scan_date');
            $table->string('status_scan')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('leave_type')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->year('year');
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('public_holiday_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('public_holiday_id')->nullable();
            $table->date('claim_date');
            $table->string('status');
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type');
            $table->date('date');
            $table->string('status');
            $table->timestamp('hr_approved_at')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $username, string $name, int $level = 3): User
    {
        return User::query()->create([
            'username' => $username,
            'name' => $name,
            'email' => strtolower($username).'@example.test',
            'password' => 'password',
            'level' => $level,
            'must_change_password' => false,
        ]);
    }

    private function employee(string $nik, string $name, ?string $pin, string $joinDate): User
    {
        $user = $this->createUser($nik, $name);

        Karyawan::query()->create([
            'nik' => $nik,
            'pin' => $pin,
            'nama_karyawan' => $name,
            'jabatan' => 'Staff',
            'departement' => 'Operations',
            'unit' => 'HQ',
            'join_date' => $joinDate,
        ]);

        return $user;
    }

    private function log(string $pin, string $dateTime, string $status): void
    {
        FingerspotAttendanceLog::query()->create([
            'pin' => $pin,
            'scan_date' => $dateTime,
            'status_scan' => $status,
        ]);
    }
}
