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
            $table->string('posisi_title')->nullable();
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
