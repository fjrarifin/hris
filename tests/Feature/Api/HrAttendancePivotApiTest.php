<?php

namespace Tests\Feature\Api;

use App\Exports\HrAttendanceExport;
use App\Http\Controllers\Api\HrAttendanceController;
use App\Models\AttendanceCorrection;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Services\HrAttendanceReportService;
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

        Carbon::setTestNow('2026-05-30 12:00:00');

        foreach ([
            'extra_off_requests',
            'public_holiday_requests',
            'attendance_corrections',
            'employee_permissions',
            'overtime_requests',
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

        $holiday = PublicHoliday::query()->create([
            'name' => 'Hari Libur Uji',
            'holiday_date' => '2026-05-24',
            'year' => 2026,
            'is_active' => true,
        ]);

        $this->log('PIN-A', '2026-05-24 08:00:00', '0');
        $this->log('PIN-A', '2026-05-24 17:30:35', '1');

        PublicHolidayRequest::query()->create([
            'user_id' => $staff->id,
            'public_holiday_id' => $holiday->id,
            'claim_date' => '2026-05-25',
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

        $this->log('PIN-A', '2026-05-29 08:00:00', '0');
        $this->log('PIN-A', '2026-05-29 17:00:00', '1');
        $this->log('PIN-A', '2026-05-29 20:00:00', '5');

        OvertimeRequest::query()->create([
            'user_id' => $staff->id,
            'date' => '2026-05-29',
            'start_time' => '18:00:00',
            'end_time' => '20:00:00',
            'reason' => 'Tutup buku',
            'status' => 'approved',
            'hr_approved_at' => now(),
        ]);

        $response = $this->getJson('/api/hr/attendance?start_date=2026-05-24&end_date=2026-05-29');

        $response
            ->assertOk()
            ->assertJsonPath('dates.0', '2026-05-24')
            ->assertJsonPath('dates.5', '2026-05-29')
            ->assertJsonPath('records.0.nik', 'EMP001')
            ->assertJsonPath('records.0.days.2026-05-24.status', 'M')
            ->assertJsonPath('records.0.days.2026-05-25.status', 'PH')
            ->assertJsonPath('records.0.days.2026-05-26.status', 'C')
            ->assertJsonPath('records.0.days.2026-05-27.status', 'S')
            ->assertJsonPath('records.0.days.2026-05-28.status', 'I')
            ->assertJsonPath('records.0.days.2026-05-29.status', 'M')
            ->assertJsonPath('records.0.total_period_days', 6)
            ->assertJsonPath('records.0.total_attendance', 4)
            ->assertJsonPath('records.0.total_work_duration_minutes', 2070)
            ->assertJsonPath('records.0.total_overtime_minutes', 120)
            ->assertJsonPath('records.0.total_ph', 1)
            ->assertJsonPath('records.0.total_leave', 1)
            ->assertJsonPath('records.0.total_sick', 1)
            ->assertJsonPath('records.0.total_permission', 1)
            ->assertJsonPath('records.0.total_national_holiday_attendance', 1)
            ->assertJsonPath('records.1.days.2026-05-26.status', 'A')
            ->assertJsonPath('records.1.total_alpha', 6)
            ->assertJsonPath('records.1.total_national_holiday_alpha', 1)
            ->assertJsonPath('summary.total_attendance', 4)
            ->assertJsonPath('summary.total_work_duration_minutes', 2070)
            ->assertJsonPath('summary.total_overtime_minutes', 120)
            ->assertJsonPath('summary.national_holiday_attendance', 1)
            ->assertJsonPath('summary.national_holiday_alpha', 1);
    }

    public function test_it_applies_hr_correction_to_missing_scan_duration(): void
    {
        $this->employee('EMP001', 'Ayu', 'PIN-A', '2026-01-01');
        $this->log('PIN-A', '2026-05-25 08:00:00', '0');

        AttendanceCorrection::query()->create([
            'nik' => 'EMP001',
            'attendance_date' => '2026-05-25',
            'corrected_scan_out' => '17:00:00',
            'has_missing_attendance_form' => true,
        ]);

        $this->getJson('/api/hr/attendance?start_date=2026-05-25&end_date=2026-05-25')
            ->assertOk()
            ->assertJsonPath('records.0.days.2026-05-25.status', 'M')
            ->assertJsonPath('records.0.days.2026-05-25.scan_out', '17:00:00')
            ->assertJsonPath('records.0.days.2026-05-25.duration_minutes', 540);
    }

    public function test_export_headings_follow_the_pivot_dates(): void
    {
        $report = app(HrAttendanceReportService::class)->report([
            'start_date' => '2026-05-24',
            'end_date' => '2026-05-26',
        ]);

        $headings = (new HrAttendanceExport($report['records'], $report['dates']))->headings();

        $this->assertContains('24/05/2026', $headings);
        $this->assertContains('26/05/2026', $headings);
        $this->assertSame('Total A Hari Libur Nasional', end($headings));

        $summaryHeadings = (new HrAttendanceExport($report['records'], $report['dates'], false))->headings();

        $this->assertNotContains('24/05/2026', $summaryHeadings);
        $this->assertContains('Total Lembur', $summaryHeadings);
    }

    public function test_payroll_overtime_is_capped_by_scan_out_and_requires_hr_approval(): void
    {
        $staff = $this->employee('EMP001', 'Ayu', 'PIN-A', '2026-01-01');
        foreach (['2026-05-24' => ['17:00:00', 0], '2026-05-25' => ['18:00:00', 0], '2026-05-26' => ['19:00:00', 60], '2026-05-27' => ['21:00:00', 120]] as $date => [$scanOut, $expected]) {
            $this->log('PIN-A', $date.' 08:00:00', '0');
            $this->log('PIN-A', $date.' '.$scanOut, '1');
            OvertimeRequest::create([
                'user_id' => $staff->id, 'date' => $date, 'start_time' => '18:00:00',
                'end_time' => '20:00:00', 'reason' => 'Test', 'status' => 'approved', 'hr_approved_at' => now(),
            ]);
            $report = app(HrAttendanceReportService::class)->report(['start_date' => $date, 'end_date' => $date]);
            $this->assertSame($expected, $report['records']->first()['total_overtime_minutes']);
        }
        OvertimeRequest::query()->update(['hr_approved_at' => null]);
        $report = app(HrAttendanceReportService::class)->report(['start_date' => '2026-05-24', 'end_date' => '2026-05-27']);
        $this->assertSame(0, $report['records']->first()['total_overtime_minutes']);
    }

    public function test_permission_recap_filters_overlapping_period_and_exports_same_records(): void
    {
        Schema::create('master_departments', function (Blueprint $table): void {
            $table->id(); $table->string('name'); $table->boolean('is_active')->default(true);
        });
        Schema::table('employee_permissions', function (Blueprint $table): void {
            $table->text('reason')->nullable();
            $table->string('document')->nullable(); $table->string('reject_reason')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
        });
        FrontendMenu::create(['key' => 'hr-permission-recap', 'label' => 'Rekap', 'path' => '/hr/permission-recap', 'allowed_levels' => '2', 'is_active' => true]);
        $staff = $this->employee('EMP001', 'Ayu', 'PIN-A', '2026-01-01');
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('test.pdf', '%PDF-test');
        $permission = EmployeePermission::create([
            'user_id' => $staff->id, 'type' => 'sakit', 'date' => '2026-05-23', 'end_date' => '2026-05-26',
            'status' => 'approved', 'hr_approved_at' => now(), 'document' => 'test.pdf',
        ]);
        EmployeePermission::create(['user_id' => $staff->id, 'type' => 'izin', 'date' => '2026-05-25', 'status' => 'pending']);
        $query = '?start_date=2026-05-24&end_date=2026-05-25&type=sakit&status=approved&search=Ayu';
        $this->getJson('/api/hr/permission-recap'.$query)->assertOk()
            ->assertJsonCount(1, 'records')->assertJsonPath('records.0.days', 2)
            ->assertJsonPath('summary.sick_days', 2)->assertJsonPath('summary.permission_days', 0);
        \Maatwebsite\Excel\Facades\Excel::fake();
        $this->get('/api/hr/permission-recap/export'.$query)->assertOk();
        \Maatwebsite\Excel\Facades\Excel::assertDownloaded('Rekap_Izin_Sakit_'.now()->format('Ymd_His').'.xlsx', function ($export) {
            return $export->collection()->count() === 1 && $export->collection()->first()[7] === 2;
        });
        $this->get('/api/hr/permission-recap/'.$permission->id.'/document')->assertOk()->assertDownload('test.pdf');
        \Illuminate\Support\Facades\Storage::disk('public')->delete('test.pdf');
        $this->get('/api/hr/permission-recap/'.$permission->id.'/document')->assertNotFound();
        $this->getJson('/api/hr/permission-recap?status=invalid')->assertUnprocessable();
        Sanctum::actingAs($staff);
        $this->getJson('/api/hr/permission-recap/'.$permission->id.'/document')->assertForbidden();
        $this->getJson('/api/hr/permission-recap')->assertForbidden();
    }

    public function test_manager_submission_hr_approval_and_duplicate_rejection(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        Schema::table('m_karyawan', function (Blueprint $table): void {
            $table->string('atasan_langsung_nik')->nullable();
            $table->string('status_karyawan')->nullable();
        });
        Schema::table('overtime_requests', function (Blueprint $table): void {
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->string('reject_reason')->nullable();
        });
        $manager = $this->employee('MGR001', 'Manager', null, '2026-01-01');
        $staff = $this->employee('EMP001', 'Ayu', 'PIN-A', '2026-01-01');
        Karyawan::where('nik', 'EMP001')->update(['atasan_langsung_nik' => 'MGR001', 'status_karyawan' => 'AKTIF']);
        $notifier = \Mockery::mock(\App\Http\Services\ApprovalNotificationService::class);
        $notifier->shouldReceive('notifyOvertimeAssigned')->once();
        $controller = new \App\Http\Controllers\Api\StaffPortalController(
            $notifier, \Mockery::mock(\App\Http\Services\ApprovalRoutingService::class),
            \Mockery::mock(\App\Http\Services\WhatsAppService::class)
        );
        $request = \Illuminate\Http\Request::create('/', 'POST', [
            'employee_niks' => ['EMP001'], 'date' => '2026-05-29', 'start_time' => '18:00', 'end_time' => '20:00', 'reason' => 'Tutup buku',
        ]);
        $request->setUserResolver(fn () => $manager);
        $this->assertSame(201, $controller->storeOvertime($request)->getStatusCode());
        $overtime = OvertimeRequest::first();
        $this->assertSame('pending', $overtime->status);
        $hr = User::where('username', 'HR001')->first();
        $decision = \Illuminate\Http\Request::create('/', 'POST', ['decision' => 'approved']);
        $decision->setUserResolver(fn () => $hr);
        $this->assertSame(200, app(\App\Http\Controllers\Api\HrApprovalController::class)->decide($decision, 'overtime', $overtime->id)->getStatusCode());
        $this->assertNotNull($overtime->fresh()->hr_approved_at);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->storeOvertime($request);
    }

    public function test_automatic_allowance_can_be_adjusted_and_saved_without_duplicate_items(): void
    {
        Schema::create('payroll_components', function (Blueprint $table): void {
            $table->id(); $table->string('nama'); $table->string('type'); $table->string('input_mode');
            $table->boolean('is_active'); $table->timestamps();
        });
        Schema::create('payrolls', function (Blueprint $table): void {
            $table->id(); $table->string('karyawan_nik'); $table->string('approval_status');
            $table->boolean('is_locked')->default(false);
            $table->integer('total_pendapatan')->default(0); $table->integer('total_potongan')->default(0);
            $table->integer('total_dibayarkan')->default(0); $table->timestamps();
        });
        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('payroll_id'); $table->unsignedBigInteger('component_id');
            $table->string('nama_item'); $table->string('type'); $table->integer('amount'); $table->timestamps();
        });
        $this->employee('EMP001', 'Ayu', 'PIN-A', '2026-01-01');
        $component = \App\Models\PayrollComponent::create(['nama' => 'Tunjangan Tidak Tetap', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => true]);
        $payroll = \App\Models\Payroll::create(['karyawan_nik' => 'EMP001', 'approval_status' => 'draft']);
        $payroll->items()->create(['component_id' => $component->id, 'nama_item' => $component->nama, 'type' => 'earning', 'amount' => 1500000]);
        $validator = \Mockery::mock(\App\Services\PayrollValidationService::class);
        $validator->shouldReceive('validateAndStore')->twice()->andReturn([]);
        $service = new \App\Services\PayrollReviewService(\Mockery::mock(\App\Services\PayrollAttendanceReadinessService::class), $validator);
        $updated = $service->updateManualAdjustments($payroll, [['component_id' => $component->id, 'amount' => 1250000]]);
        $this->assertSame(1, $updated->items->count());
        $this->assertEquals(1250000, $updated->total_dibayarkan);
        $updated = $service->updateManualAdjustments($updated, [['component_id' => $component->id, 'amount' => 0]]);
        $this->assertCount(0, $updated->items);
        $this->assertEquals(0, $updated->total_dibayarkan);
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

        Schema::create('extra_off_requests', function (Blueprint $table): void {
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
            $table->date('end_date')->nullable();
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
