<?php

namespace Tests\Feature\Api;

use App\Http\Services\ApprovalNotificationService;
use App\Models\FingerspotAttendanceLog;
use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class StaffPublicHolidayApiTest extends TestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'employee_ph_adjustments',
            'employee_ph_balances',
            'employee_extra_offs',
            'extra_off_requests',
            'attendance_corrections',
            'frontend_menu_user_access',
            'frontend_menus',
            'public_holiday_requests',
            'public_holidays',
            'leave_requests',
            'fingerspot_attendance_logs',
            'm_karyawan',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

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
            $table->string('nama_atasan_langsung')->nullable();
            $table->string('atasan_langsung_nik', 30)->nullable();
            $table->string('atasan_tidak_langsung_nik', 30)->nullable();
            $table->string('posisi_title')->nullable();
            $table->date('join_date')->nullable();
            $table->string('status_karyawan')->default('AKTIF');
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
            $table->foreignId('user_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('public_holidays', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->date('holiday_date');
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('public_holiday_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('public_holiday_id');
            $table->date('claim_date');
            $table->string('status');
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('hr_approved_by')->nullable();
            $table->text('reject_reason')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->uuid('approval_token')->nullable();
            $table->timestamp('approval_token_expires_at')->nullable();
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

        Schema::create('employee_ph_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->unsignedBigInteger('public_holiday_id')->nullable();
            $table->integer('days');
            $table->timestamps();
        });
        Schema::create('employee_ph_balances', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('public_holiday_id');
            $table->date('holiday_date');
            $table->string('holiday_name');
            $table->integer('days');
            $table->string('source');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('extra_off_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('source_period_start');
            $table->date('source_period_end');
            $table->string('approval_token')->nullable();
            $table->timestamp('approval_token_expires_at')->nullable();
            $table->date('claim_date');
            $table->string('status');
            $table->string('reject_reason')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_extra_offs', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('periode_start');
            $table->date('periode_end');
            $table->integer('days');
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_07_130000_add_ph_conversion_to_extra_offs.php'))->up();
        Schema::table('employee_extra_offs', function (Blueprint $table): void {
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
        });
        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('nik');
            $table->date('attendance_date');
            $table->string('correction_type');
            $table->timestamps();
        });

        $this->createMenu('staff-extra-off', '/staff/extra-off');
        $this->createMenu('staff-public-holiday', '/staff/public-holiday');
        $this->createMenu('staff-approvals', '/staff/approvals');

        $this->employee = $this->createEmployeeUser('EMP001', 'Karyawan Satu', 'PIN-001');

        Sanctum::actingAs($this->employee);
        Carbon::setTestNow('2026-06-02 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_historical_ph_is_available_without_scan_while_new_ph_requires_attendance(): void
    {
        $historical = $this->createHoliday('Kenaikan Yesus Kristus', '2026-05-14');
        $newWithoutScan = $this->createHoliday('Hari Raya Idul Adha', '2026-05-27');
        $newWithScan = $this->createHoliday('Hari Raya Waisak', '2026-05-31');

        FingerspotAttendanceLog::query()->create([
            'pin' => 'PIN-001',
            'scan_date' => '2026-05-31 08:00:00',
        ]);

        $this->getJson('/api/staff/public-holiday')
            ->assertOk()
            ->assertJsonPath('balance', 2)
            ->assertJsonCount(2, 'holidays')
            ->assertJsonPath('holidays.0.id', $newWithScan->id)
            ->assertJsonPath('holidays.1.id', $historical->id)
            ->assertJsonMissing(['id' => $newWithoutScan->id]);
    }

    public function test_employee_can_submit_historical_ph_without_scan_but_cannot_submit_new_ph_without_scan(): void
    {
        $historical = $this->createHoliday('Kenaikan Yesus Kristus', '2026-05-14');
        $newHoliday = $this->createHoliday('Hari Raya Idul Adha', '2026-05-27');

        $this->mock(ApprovalNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyManager')->once();
        });

        $this->postJson('/api/staff/public-holiday', [
            'public_holiday_id' => $historical->id,
            'claim_date' => '2026-06-03',
        ])->assertCreated();

        $this->postJson('/api/staff/public-holiday', [
            'public_holiday_id' => $newHoliday->id,
            'claim_date' => '2026-06-03',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('public_holiday_id');

        $this->assertDatabaseHas('public_holiday_requests', [
            'user_id' => $this->employee->id,
            'public_holiday_id' => $historical->id,
        ]);
        $this->assertDatabaseMissing('public_holiday_requests', [
            'user_id' => $this->employee->id,
            'public_holiday_id' => $newHoliday->id,
        ]);
    }

    public function test_manager_can_approve_historical_ph_but_not_new_ph_without_scan(): void
    {
        Notification::fake();

        $manager = $this->createEmployeeUser('MGR001', 'Atasan Satu', 'PIN-MGR');
        Karyawan::query()->where('nik', $this->employee->username)->update([
            'nama_atasan_langsung' => $manager->name,
            'atasan_langsung_nik' => $manager->username,
        ]);
        Sanctum::actingAs($manager);

        $historicalRequest = $this->createPhRequest($this->createHoliday('Hari Buruh', '2026-05-01'));
        $newRequest = $this->createPhRequest($this->createHoliday('Hari Raya Idul Adha', '2026-05-27'));

        $this->mock(ApprovalNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyIndirectManagerOfDirectManagerDecision')->once();
            $mock->shouldReceive('notifyHrGroups')->once();
        });

        $this->postJson('/api/staff/approvals/ph/'.$historicalRequest->id, [
            'decision' => 'approved',
        ])->assertOk();

        $this->postJson('/api/staff/approvals/ph/'.$newRequest->id, [
            'decision' => 'approved',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('decision');

        $this->assertDatabaseHas('public_holiday_requests', [
            'id' => $historicalRequest->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('public_holiday_requests', [
            'id' => $newRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_manager_ph_submission_goes_directly_to_hr(): void
    {
        $manager = $this->createEmployeeUser('MGR002', 'Manager Operasional', 'PIN-M2', 'Manager');
        $holiday = $this->createHoliday('Hari Buruh', '2026-05-01');
        Sanctum::actingAs($manager);

        $this->mock(ApprovalNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyHrGroups')
                ->once()
                ->with(\Mockery::type(PublicHolidayRequest::class), 'PH');
            $mock->shouldNotReceive('notifyManager');
        });

        $this->postJson('/api/staff/public-holiday', [
            'public_holiday_id' => $holiday->id,
            'claim_date' => '2026-06-03',
        ])->assertCreated();

        $this->assertDatabaseHas('public_holiday_requests', [
            'user_id' => $manager->id,
            'status' => 'pending',
            'approval_token' => null,
        ]);

        $this->assertNotNull(
            PublicHolidayRequest::query()->where('user_id', $manager->id)->value('manager_approved_at')
        );
    }

    public function test_attendance_returns_ph_balance_and_selectable_holiday_in_same_response(): void
    {
        $holiday = $this->createHoliday('Hari Buruh', '2026-05-01');
        $ph = $this->createPhRequest($holiday);
        $ph->update(['status' => 'approved', 'claim_date' => '2026-06-01']);
        FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => '2026-06-01 08:00:00']);

        $this->getJson('/api/staff/public-holiday')->assertOk()
            ->assertJsonPath('balance', 1)
            ->assertJsonPath('holidays.0.id', $holiday->id)
            ->assertJsonPath('requests.0.status', 'cancelled');
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 1);
    }

    public function test_pending_and_rejected_ph_reserve_and_release_balance(): void
    {
        $holiday = $this->createHoliday('Hari Buruh', '2026-05-01');
        $ph = $this->createPhRequest($holiday);
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 0);
        $ph->update(['status' => 'rejected']);
        $this->getJson('/api/staff/public-holiday')->assertOk()
            ->assertJsonPath('balance', 1)->assertJsonPath('holidays.0.id', $holiday->id);
    }

    public function test_scheduled_reconciliation_catches_late_scans_and_extra_off_without_portal_visit(): void
    {
        $ph = $this->createPhRequest($this->createHoliday('Hari Buruh', '2026-05-01'));
        $ph->update(['status' => 'approved', 'claim_date' => '2026-05-29']);
        $eo = \App\Models\ExtraOffRequest::create([
            'user_id' => $this->employee->id,
            'source_period_start' => '2026-05-01', 'source_period_end' => '2026-05-31',
            'claim_date' => '2026-05-30', 'status' => 'pending',
        ]);
        foreach (['2026-05-29', '2026-05-30'] as $date) {
            FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => $date.' 08:00:00']);
        }
        $this->artisan('absence:auto-cancel-attended')->assertSuccessful();
        $this->assertSame('cancelled', $ph->fresh()->status);
        $this->assertSame('cancelled', $eo->fresh()->status);
        $this->assertSame(0, app(\App\Services\AttendedDayOffCancellationService::class)->reconcile());
    }

    public function test_hr_day_off_correction_prevents_auto_cancellation(): void
    {
        $ph = $this->createPhRequest($this->createHoliday('Hari Buruh', '2026-05-01'));
        $ph->update(['status' => 'approved', 'claim_date' => '2026-06-01']);
        FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => '2026-06-01 08:00:00']);
        \App\Models\AttendanceCorrection::create([
            'nik' => 'EMP001', 'attendance_date' => '2026-06-01', 'correction_type' => 'day_off',
        ]);
        $this->artisan('absence:auto-cancel-attended')->assertSuccessful();
        $this->assertSame('approved', $ph->fresh()->status);
    }

    public function test_recorded_ph_and_extra_off_survive_changed_join_date(): void
    {
        $holiday = $this->createHoliday('Idul Adha', '2026-05-27');
        app(\App\Services\PublicHolidayBalanceService::class)->grantManually('EMP001', $holiday->id, 'Hak lama', null);
        \App\Models\EmployeeExtraOff::create([
            'karyawan_nik' => 'EMP001', 'periode_start' => '2026-05-01',
            'periode_end' => '2026-05-31', 'days' => 2,
        ]);
        Karyawan::where('nik', 'EMP001')->update(['join_date' => '2026-06-01']);
        $this->getJson('/api/staff/public-holiday')->assertOk()
            ->assertJsonPath('balance', 1)->assertJsonPath('holidays.0.id', $holiday->id);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 2);

        $this->mock(ApprovalNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyManager')->once();
        });
        $this->postJson('/api/staff/public-holiday', [
            'public_holiday_id' => $holiday->id, 'claim_date' => '2026-06-10',
        ])->assertCreated();
    }

    public function test_hr_approval_then_attendance_returns_balance_and_hr_rejection_releases_it(): void
    {
        Notification::fake();
        $ph = $this->createPhRequest($this->createHoliday('Hari Buruh', '2026-05-01'));
        $ph->update(['manager_approved_at' => now(), 'status' => 'approved', 'claim_date' => '2026-06-02']);
        $hr = $this->createEmployeeUser('HR001', 'HRD', 'PIN-HR');
        $hr->update(['level' => 2]);
        Sanctum::actingAs($hr);
        $this->postJson('/api/hr/approvals/ph/'.$ph->id, ['decision' => 'approved'])->assertOk();
        $this->assertNotNull($ph->fresh()->hr_approved_at);
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 0);
        FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => '2026-06-02 08:00:00']);
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 1);

        $second = $this->createPhRequest($ph->holiday);
        $second->update(['manager_approved_at' => now(), 'status' => 'approved']);
        Sanctum::actingAs($hr);
        $this->postJson('/api/hr/approvals/ph/'.$second->id, [
            'decision' => 'rejected', 'reason' => 'Tidak jadi diambil',
        ])->assertOk();
        Sanctum::actingAs($this->employee);
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 1);
    }

    public function test_contract_renewal_resets_only_annual_leave_not_ph_or_extra_off(): void
    {
        Schema::dropIfExists('t_kontrak_karyawan');
        Schema::dropIfExists('leave_accruals');
        Schema::create('t_kontrak_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status_kontrak');
        });
        Schema::create('leave_accruals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('nik');
            $table->integer('year');
            $table->integer('month');
            $table->date('accrued_at');
            $table->date('expired_at');
            $table->integer('days');
            $table->boolean('is_used')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Karyawan::where('nik', 'EMP001')->update(['join_date' => '2024-01-15']);
        \Illuminate\Support\Facades\DB::table('t_kontrak_karyawan')->insert([
            'nik' => 'EMP001', 'start_date' => '2026-06-01',
            'end_date' => '2027-05-31', 'status_kontrak' => 'AKTIF',
        ]);
        $oldLeave = \App\Models\LeaveAccrual::create([
            'user_id' => $this->employee->id, 'nik' => 'EMP001', 'year' => 2026, 'month' => 5,
            'accrued_at' => '2026-05-15', 'expired_at' => '2026-05-31', 'days' => 1,
        ]);
        $holiday = $this->createHoliday('Hari Buruh', '2026-05-01');
        app(\App\Services\PublicHolidayBalanceService::class)->grantManually('EMP001', $holiday->id, 'Hak lama', null);
        \App\Models\EmployeeExtraOff::create([
            'karyawan_nik' => 'EMP001', 'periode_start' => '2026-05-01',
            'periode_end' => '2026-05-31', 'days' => 2,
        ]);
        app(\App\Http\Controllers\LeaveAccrualService::class)->generateMonthly($this->employee->fresh());
        $this->assertDatabaseMissing('leave_accruals', ['id' => $oldLeave->id]);
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 1);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 2);
    }

    public function test_extra_off_returns_to_same_source_after_attendance_without_double_credit(): void
    {
        \App\Models\EmployeeExtraOff::create([
            'karyawan_nik' => 'EMP001', 'periode_start' => '2026-05-01',
            'periode_end' => '2026-05-31', 'days' => 2,
        ]);
        $eo = \App\Models\ExtraOffRequest::create([
            'user_id' => $this->employee->id, 'source_period_start' => '2026-05-01',
            'source_period_end' => '2026-05-31', 'claim_date' => '2026-06-01', 'status' => 'approved',
        ]);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 1);
        FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => '2026-06-01 08:00:00']);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 2);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 2);
        $this->assertSame('cancelled', $eo->fresh()->status);
        $this->assertSame(2, (int) \App\Models\EmployeeExtraOff::first()->days);
    }

    public function test_existing_expiry_rules_are_not_extended_by_contract_carryover(): void
    {
        $holiday = $this->createHoliday('Hari Buruh', '2026-05-01');
        app(\App\Services\PublicHolidayBalanceService::class)->grantManually('EMP001', $holiday->id, 'Hak lama', null);
        \App\Models\EmployeeExtraOff::create([
            'karyawan_nik' => 'EMP001', 'periode_start' => '2026-05-01',
            'periode_end' => '2026-05-31', 'days' => 2,
        ]);
        Carbon::setTestNow('2026-09-02 09:00:00');
        $this->getJson('/api/staff/public-holiday')->assertOk()->assertJsonPath('balance', 0);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 0);
    }

    public function test_expired_ph_converts_once_with_period_cutoff_and_separate_payroll_usage(): void
    {
        $this->mock(ApprovalNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyManager')->once();
        });
        $holiday = $this->createHoliday('PH lama', '2026-02-01');
        $claim = PublicHolidayRequest::create([
            'user_id' => $this->employee->id, 'public_holiday_id' => $holiday->id,
            'claim_date' => '2026-05-01', 'status' => 'approved',
        ]);
        FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => '2026-05-01 08:00:00']);
        Carbon::setTestNow('2026-06-25 09:00:00');
        $payroll = \App\Models\EmployeeExtraOff::create([
            'karyawan_nik' => 'EMP001', 'periode_start' => '2026-06-25',
            'periode_end' => '2026-07-24', 'days' => 2, 'source' => 'payroll',
        ]);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 3);
        $replacement = \App\Models\EmployeeExtraOff::where('public_holiday_request_id', $claim->id)->sole();
        $this->assertSame('2026-07-24', $replacement->expires_at->toDateString());
        $this->assertSame('2026-06-25', $replacement->periode_start->toDateString());
        $this->assertSame('cancelled', $claim->fresh()->status);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 3);
        $this->assertSame(1, \App\Models\EmployeeExtraOff::where('source', 'ph_conversion')->count());
        // Claim beyond conversion cutoff is rejected even though regular EO lasts three months.
        $this->postJson('/api/staff/extra-off', [
            'source_id' => $replacement->id, 'source_period_start' => '2026-06-25',
            'source_period_end' => '2026-07-24', 'claim_date' => '2026-07-25',
        ])->assertUnprocessable()->assertJsonValidationErrors('claim_date');
        Schema::dropIfExists('employee_permissions');
        Schema::create('employee_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('date');
            $table->date('end_date')->nullable();
            $table->string('status');
        });
        $this->postJson('/api/staff/extra-off', [
            'source_id' => $replacement->id, 'source_period_start' => '2026-06-25',
            'source_period_end' => '2026-07-24', 'claim_date' => '2026-07-24',
        ])->assertCreated();
        $this->assertSame($replacement->id, (int) \App\Models\ExtraOffRequest::sole()->employee_extra_off_id);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 2);
        $this->assertSame(2, (int) $payroll->fresh()->days);
        Carbon::setTestNow('2026-07-24 23:59:59');
        $this->assertFalse(now()->gt($replacement->expirationDate()));
        \App\Models\ExtraOffRequest::sole()->assertReplacementIsValid();
        Carbon::setTestNow('2026-07-25 00:00:00');
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 2);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        \App\Models\ExtraOffRequest::sole()->assertReplacementIsValid();
    }

    public function test_conversion_on_the_24th_expires_that_day_and_active_ph_is_not_converted(): void
    {
        Carbon::setTestNow('2026-06-24 12:00:00');
        foreach (['2026-02-01', '2026-05-01'] as $date) {
            $holiday = $this->createHoliday('PH '.$date, $date);
            PublicHolidayRequest::create([
                'user_id' => $this->employee->id, 'public_holiday_id' => $holiday->id,
                'claim_date' => Carbon::parse($date)->addDays(20), 'status' => 'pending',
            ]);
            FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => Carbon::parse($date)->addDays(20)->setTime(8, 0)]);
        }
        app(\App\Services\AttendedDayOffCancellationService::class)->reconcile($this->employee->id);
        $replacement = \App\Models\EmployeeExtraOff::sole();
        $this->assertSame('2026-06-24', $replacement->expires_at->toDateString());
        $this->assertSame(2, PublicHolidayRequest::where('status', 'cancelled')->count());
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 1);
        Carbon::setTestNow('2026-06-25 00:00:00');
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 0);
    }

    public function test_expired_ph_without_scan_or_with_day_off_correction_does_not_convert(): void
    {
        Carbon::setTestNow('2026-06-25 09:00:00');
        foreach (['2026-02-01', '2026-02-02', '2026-02-03'] as $i => $date) {
            $holiday = $this->createHoliday('PH '.$date, $date);
            $claimDate = Carbon::parse($date)->addDays($i === 2 ? 100 : 20);
            PublicHolidayRequest::create([
                'user_id' => $this->employee->id, 'public_holiday_id' => $holiday->id,
                'claim_date' => $claimDate, 'status' => 'approved',
            ]);
            if ($i > 0) {
                FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => $claimDate->copy()->setTime(8, 0)]);
            }
            if ($i === 1) {
                \App\Models\AttendanceCorrection::create([
                    'nik' => 'EMP001', 'attendance_date' => $claimDate, 'correction_type' => 'day_off',
                ]);
            }
        }
        $this->assertSame(1, app(\App\Services\AttendedDayOffCancellationService::class)->reconcile($this->employee->id));
        $this->assertSame(0, \App\Models\EmployeeExtraOff::count());
    }

    public function test_replacement_extra_off_attendance_restores_same_source_without_extending_expiry(): void
    {
        Carbon::setTestNow('2026-06-25 09:00:00');
        $source = \App\Models\EmployeeExtraOff::create([
            'karyawan_nik' => 'EMP001', 'periode_start' => '2026-06-25', 'periode_end' => '2026-07-24',
            'expires_at' => '2026-07-24', 'days' => 1, 'source' => 'ph_conversion', 'public_holiday_request_id' => 123,
        ]);
        $request = \App\Models\ExtraOffRequest::create([
            'user_id' => $this->employee->id, 'employee_extra_off_id' => $source->id,
            'source_period_start' => '2026-06-25', 'source_period_end' => '2026-07-24',
            'claim_date' => '2026-06-25', 'status' => 'approved',
        ]);
        FingerspotAttendanceLog::create(['pin' => 'PIN-001', 'scan_date' => '2026-06-25 08:00:00']);
        $this->getJson('/api/staff/extra-off')->assertOk()->assertJsonPath('balance', 1);
        $this->assertSame('cancelled', $request->fresh()->status);
        $this->assertSame(1, \App\Models\EmployeeExtraOff::count());
        $this->assertSame('2026-07-24', $source->fresh()->expires_at->toDateString());
    }

    public function test_conversion_migration_can_be_rolled_back_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_11_07_130000_add_ph_conversion_to_extra_offs.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('employee_extra_offs', 'expires_at'));
        $this->assertFalse(Schema::hasColumn('extra_off_requests', 'employee_extra_off_id'));
        $migration->up();
        $this->assertTrue(Schema::hasColumn('employee_extra_offs', 'public_holiday_request_id'));
        $this->assertTrue(Schema::hasColumn('extra_off_requests', 'employee_extra_off_id'));
    }

    private function createMenu(string $key, string $path): void
    {
        FrontendMenu::query()->create([
            'key' => $key,
            'label' => $key,
            'path' => $path,
            'allowed_levels' => '3',
            'is_active' => true,
        ]);
    }

    private function createEmployeeUser(string $nik, string $name, string $pin, ?string $positionTitle = null): User
    {
        $user = User::query()->create([
            'username' => $nik,
            'name' => $name,
            'email' => strtolower($nik).'@example.test',
            'password' => 'password',
            'level' => 3,
            'must_change_password' => false,
        ]);

        Karyawan::query()->create([
            'nik' => $nik,
            'pin' => $pin,
            'nama_karyawan' => $name,
            'posisi_title' => $positionTitle,
        ]);

        return $user;
    }

    private function createHoliday(string $name, string $date): PublicHoliday
    {
        return PublicHoliday::query()->create([
            'name' => $name,
            'holiday_date' => $date,
            'year' => 2026,
            'is_active' => true,
        ]);
    }

    private function createPhRequest(PublicHoliday $holiday): PublicHolidayRequest
    {
        return PublicHolidayRequest::query()->create([
            'user_id' => $this->employee->id,
            'public_holiday_id' => $holiday->id,
            'claim_date' => '2026-06-03',
            'status' => 'pending',
        ]);
    }
}
