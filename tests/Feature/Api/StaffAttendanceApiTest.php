<?php

namespace Tests\Feature\Api;

use App\Models\EmployeeDailySchedule;
use App\Models\FingerspotAttendanceLog;
use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use Carbon\Carbon;
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
        Schema::dropIfExists('extra_off_requests');
        Schema::dropIfExists('public_holiday_requests');
        Schema::dropIfExists('public_holidays');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('employee_daily_schedules');
        Schema::dropIfExists('attendance_schedule_categories');
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

        Schema::create('attendance_schedule_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_workday')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_daily_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('schedule_date');
            $table->string('schedule_code');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('leave_type')->default('lainnya');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('public_holiday_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('public_holiday_id');
            $table->date('claim_date');
            $table->string('status');
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('extra_off_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->date('source_period_start');
            $table->date('source_period_end');
            $table->date('claim_date');
            $table->string('status');
            $table->timestamp('manager_approved_at')->nullable();
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    public function test_employee_can_filter_attendance_by_quick_range(): void
    {
        Carbon::setTestNow('2026-06-08 10:00:00');

        $this->createLog('PIN-001', '2026-06-01 08:00:00');
        $this->createLog('PIN-001', '2026-06-02 08:00:00');
        $this->createLog('PIN-001', '2026-06-08 08:00:00');

        $this->getJson('/api/staff/attendance?range=7d')
            ->assertOk()
            ->assertJsonPath('filters.range', '7d')
            ->assertJsonPath('filters.start_date', '2026-06-02')
            ->assertJsonPath('filters.end_date', '2026-06-08')
            ->assertJsonCount(2, 'records');
    }

    public function test_employee_can_filter_attendance_by_current_month(): void
    {
        Carbon::setTestNow('2026-06-08 10:00:00');

        $this->createLog('PIN-001', '2026-05-31 08:00:00');
        $this->createLog('PIN-001', '2026-06-08 08:00:00');

        $this->getJson('/api/staff/attendance?range=month')
            ->assertOk()
            ->assertJsonPath('filters.range', 'month')
            ->assertJsonPath('filters.start_date', '2026-06-01')
            ->assertJsonPath('filters.end_date', '2026-06-30')
            ->assertJsonCount(1, 'records');
    }

    public function test_approved_public_holiday_overrides_calendar_status_without_removing_schedule(): void
    {
        $user = User::query()->where('username', 'HPP25120147')->firstOrFail();
        $holiday = PublicHoliday::query()->create([
            'name' => 'Hari Lahir Pancasila',
            'holiday_date' => '2026-06-01',
            'year' => 2026,
            'is_active' => true,
        ]);

        EmployeeDailySchedule::query()->create([
            'karyawan_nik' => $user->username,
            'schedule_date' => '2026-06-08',
            'schedule_code' => 'P1',
        ]);

        PublicHolidayRequest::query()->create([
            'user_id' => $user->id,
            'public_holiday_id' => $holiday->id,
            'claim_date' => '2026-06-08',
            'status' => 'approved',
            'manager_approved_at' => Carbon::parse('2026-06-02 10:00:00'),
        ]);

        $this->getJson('/api/staff/attendance?start_date=2026-06-08&end_date=2026-06-08')
            ->assertOk()
            ->assertJsonCount(1, 'records')
            ->assertJsonPath('records.0.date', '2026-06-08')
            ->assertJsonPath('records.0.status', 'public_holiday')
            ->assertJsonPath('records.0.status_label', 'PH')
            ->assertJsonPath('records.0.attendance_source', 'approved_absence')
            ->assertJsonPath('records.0.has_scan', false)
            ->assertJsonPath('records.0.schedule_code', 'P1')
            ->assertJsonPath('summary.attendance_days', 0);
    }

    private function createLog(string $pin, string $scanDate): void
    {
        FingerspotAttendanceLog::query()->create([
            'pin' => $pin,
            'scan_date' => $scanDate,
        ]);
    }
}
