<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceScheduleCategory;
use App\Models\EmployeeDailySchedule;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrDashboardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-05-24 10:00:00');

        foreach ([
            't_kontrak_karyawan',
            'overtime_requests',
            'public_holiday_requests',
            'employee_permissions',
            'leave_requests',
            'fingerspot_attendance_logs',
            'attendance_corrections',
            'employee_daily_schedules',
            'attendance_schedule_categories',
            'm_karyawan',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        $this->createTables();

        $hr = $this->createUser('HR001', 'HR Dashboard', 2);
        Sanctum::actingAs($hr);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hr_dashboard_summarizes_live_operational_data(): void
    {
        $manager = $this->employee('MGR001', 'Manager Marketing', 'PIN-M', 'Manager', 'Marketing');
        $assistant = $this->employee('ASM001', 'Asisten Sales', 'PIN-A', 'Asst. Manager', 'Sales');
        $staff = $this->employee('EMP001', 'Staf Operasional', 'PIN-E', 'Staff', 'Operations');
        $leader = $this->employee('LDR001', 'Leader Marketing', 'PIN-L', 'Leader', 'Marketing');
        $this->employee('EMP002', 'PIN Belum Terhubung', null, 'Staff', 'Operations');
        $this->employee('EMP003', 'Tidak Hadir', 'PIN-N', 'Staff', 'Operations');

        $this->log('PIN-M', '2026-05-24 08:00:00', '0');
        $this->log('PIN-M', '2026-05-24 17:00:00', '1');
        $this->log('PIN-A', '2026-05-24 08:10:00', '0');
        $this->log('PIN-L', '2026-05-24 08:20:00', '0');
        $this->log('PIN-UNKNOWN', '2026-05-24 08:15:00', '0');

        $work = AttendanceScheduleCategory::query()->create([
            'code' => 'P1',
            'name' => 'Pagi',
            'type' => 'work',
            'is_workday' => true,
            'is_active' => true,
        ]);

        foreach (['MGR001', 'EMP001', 'EMP002', 'EMP003'] as $nik) {
            EmployeeDailySchedule::query()->create([
                'karyawan_nik' => $nik,
                'schedule_date' => '2026-05-23',
                'schedule_category_id' => $work->id,
                'schedule_code' => 'P1',
            ]);
        }

        $this->log('PIN-M', '2026-05-23 08:00:00', '0');
        $this->log('PIN-E', '2026-05-23 17:00:00', '1');

        LeaveRequest::query()->create([
            'user_id' => $staff->id,
            'leave_type' => 'cuti_tahunan',
            'start_date' => '2026-05-24',
            'end_date' => '2026-05-25',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        PublicHolidayRequest::query()->create([
            'user_id' => $assistant->id,
            'claim_date' => '2026-05-24',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        EmployeePermission::query()->create([
            'user_id' => $staff->id,
            'type' => 'izin',
            'date' => '2026-05-24',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        EmployeePermission::query()->create([
            'user_id' => $manager->id,
            'type' => 'sakit',
            'date' => '2026-05-24',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        OvertimeRequest::query()->create([
            'user_id' => $staff->id,
            'date' => '2026-05-24',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => 'Penutupan laporan',
            'status' => 'pending',
        ]);

        DB::table('t_kontrak_karyawan')->insert([
            'nik' => 'EMP001',
            'kontrak_ke' => 2,
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status_kontrak' => 'AKTIF',
        ]);

        $this->getJson('/api/hr/dashboard')
            ->assertOk()
            ->assertJsonPath('summary.total_employees', 6)
            ->assertJsonPath('summary.active_employees', 1)
            ->assertJsonPath('summary.attendance_today', 3)
            ->assertJsonPath('summary.scan_pins_today', 4)
            ->assertJsonPath('attendance.mapped_employee_count', 3)
            ->assertJsonPath('attendance.unmapped_pin_count', 1)
            ->assertJsonPath('attendance.by_department.0.total', 2)
            ->assertJsonPath('attendance.by_department.0.employees.0.name', 'Manager Marketing')
            ->assertJsonCount(1, 'attendance.managers_present')
            ->assertJsonPath('attendance.managers_present.0.scan_in', '08:00:00')
            ->assertJsonPath('attendance.managers_present.0.scan_out', '17:00:00')
            ->assertJsonCount(1, 'attendance.assistant_managers_present')
            ->assertJsonCount(3, 'attendance.management_present')
            ->assertJsonPath('summary.leave_today', 1)
            ->assertJsonPath('summary.public_holiday_today', 1)
            ->assertJsonPath('summary.permission_today', 1)
            ->assertJsonPath('summary.sick_today', 1)
            ->assertJsonPath('permission_today.0.name', 'Staf Operasional')
            ->assertJsonPath('sick_today.0.name', 'Manager Marketing')
            ->assertJsonPath('summary.overtime_today', 1)
            ->assertJsonPath('overtime_today.0.department', 'Operations')
            ->assertJsonPath('yesterday_incomplete_attendance.unlinked_pin_count', 1)
            ->assertJsonCount(2, 'yesterday_incomplete_attendance.records')
            ->assertJsonPath('yesterday_incomplete_attendance.records.0.missing_scan_out', true)
            ->assertJsonPath('yesterday_incomplete_attendance.records.0.whatsapp_notification_status', 'Sudah diberikan notif WhatsApp')
            ->assertJsonPath('yesterday_incomplete_attendance.records.1.missing_scan_in', true)
            ->assertJsonPath('summary.expiring_contracts', 1)
            ->assertJsonPath('expiring_contracts.records.0.nik', 'EMP001');
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

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('pin')->nullable();
            $table->string('nama_karyawan');
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('posisi_title')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('status_karyawan')->nullable();
            $table->timestamps();
        });

        Schema::create('fingerspot_attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('pin');
            $table->dateTime('scan_date');
            $table->string('status_scan')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('nik');
            $table->date('attendance_date');
            $table->time('corrected_scan_in')->nullable();
            $table->time('corrected_scan_out')->nullable();
            $table->boolean('has_missing_attendance_form')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_schedule_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->boolean('is_workday');
            $table->boolean('is_active');
            $table->timestamps();
        });

        Schema::create('employee_daily_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('schedule_date');
            $table->unsignedBigInteger('schedule_category_id')->nullable();
            $table->string('schedule_code');
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

        Schema::create('public_holiday_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
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

        Schema::create('overtime_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->text('reason');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('t_kontrak_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik');
            $table->unsignedInteger('kontrak_ke');
            $table->date('start_date')->nullable();
            $table->date('end_date');
            $table->string('status_kontrak');
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

    private function employee(string $nik, string $name, ?string $pin, string $positionTitle, string $department): User
    {
        $user = $this->createUser($nik, $name);

        Karyawan::query()->create([
            'nik' => $nik,
            'pin' => $pin,
            'nama_karyawan' => $name,
            'jabatan' => $positionTitle,
            'posisi_title' => $positionTitle,
            'departement' => $department,
            'status_karyawan' => 'AKTIF',
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
