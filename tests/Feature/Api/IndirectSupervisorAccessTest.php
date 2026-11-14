<?php

namespace Tests\Feature\Api;

use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndirectSupervisorAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        \Carbon\Carbon::setTestNow('2026-06-02 09:00:00');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->integer('level')->default(3);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });
        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            foreach (['pin', 'jabatan', 'posisi', 'posisi_title', 'departement', 'divisi', 'unit', 'atasan_langsung_nik', 'atasan_tidak_langsung_nik'] as $column) {
                $table->string($column)->nullable();
            }
            $table->string('status_karyawan')->default('AKTIF');
            $table->timestamps();
        });
        Schema::create('frontend_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->string('label');
            $table->string('path');
            $table->string('icon')->nullable();
            $table->string('allowed_levels');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('frontend_menu_user_access', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('frontend_menu_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_allowed');
        });
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
            $table->text('reject_reason')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->unsignedBigInteger('manager_approved_by')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->unsignedBigInteger('hr_approved_by')->nullable();
            $table->timestamps();
        });

        foreach (['public_holiday_requests', 'extra_off_requests', 'employee_permissions', 'employee_daily_schedules', 'attendance_schedule_categories', 't_kontrak_karyawan'] as $name) {
            Schema::dropIfExists($name);
        }

        foreach (['public_holiday_requests', 'extra_off_requests', 'employee_permissions'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('public_holiday_id')->nullable();
                $table->string('type')->default('izin');
                $table->date('claim_date')->nullable();
                $table->date('date')->nullable();
                $table->date('end_date')->nullable();
                $table->text('reason')->nullable();
                $table->string('status')->default('pending');
                $table->timestamp('manager_approved_at')->nullable();
                $table->unsignedBigInteger('manager_approved_by')->nullable();
                $table->timestamp('hr_approved_at')->nullable();
                $table->unsignedBigInteger('hr_approved_by')->nullable();
                $table->text('reject_reason')->nullable();
                $table->timestamps();
            });
        }

        Schema::create('attendance_schedule_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('type')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_workday')->default(true);
        });
        Schema::create('employee_daily_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('schedule_date');
            $table->unsignedBigInteger('schedule_category_id');
            $table->string('schedule_code');
            $table->string('source');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();
        });
        Schema::create('t_kontrak_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik');
            $table->integer('kontrak_ke')->default(1);
            $table->string('jenis_kontrak')->default('PKWT');
            $table->string('status_kontrak')->default('AKTIF');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('document')->nullable();
        });

        foreach (['staff-approvals', 'staff-team-schedules', 'staff-team-attendances', 'staff-contracts'] as $key) {
            DB::table('frontend_menus')->insert([
                'key' => $key, 'label' => $key, 'path' => '/'.$key,
                'allowed_levels' => '3', 'is_active' => true,
            ]);
        }

        $this->supervisor('DIRECT');
        $this->supervisor('INDIRECT');
        $this->supervisor('OUTSIDER');
        $this->supervisor('EMP001');
        Karyawan::where('nik', 'EMP001')->update([
            'atasan_langsung_nik' => 'DIRECT',
            'atasan_tidak_langsung_nik' => 'INDIRECT',
        ]);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    private function supervisor(string $nik): User
    {
        Karyawan::firstOrCreate(['nik' => $nik], ['nama_karyawan' => $nik, 'jabatan' => 'Staff']);

        return User::firstOrCreate(['username' => $nik], [
            'name' => $nik, 'email' => strtolower($nik).'@example.com',
            'password' => bcrypt('password'), 'level' => 3,
        ]);
    }

    private function seedRequests(): void
    {
        $userId = User::where('username', 'EMP001')->value('id');
        DB::table('leave_requests')->insert([
            'user_id' => $userId, 'leave_type' => 'cuti_tahunan',
            'start_date' => '2026-06-03', 'end_date' => '2026-06-03', 'status' => 'pending',
        ]);
        foreach (['public_holiday_requests', 'extra_off_requests', 'employee_permissions'] as $table) {
            DB::table($table)->insert(['user_id' => $userId, 'status' => 'pending']);
        }
    }

    public function test_indirect_supervisor_can_view_requests_but_cannot_decide_or_notify_cancellation(): void
    {
        $this->seedRequests();
        Sanctum::actingAs($this->supervisor('INDIRECT'));
        $response = $this->getJson('/api/staff/approvals')->assertOk()
            ->assertJsonCount(4, 'requests')->assertJsonPath('pending_count', 0);
        foreach ($response->json('requests') as $item) {
            $this->assertFalse($item['can_decide']);
            $this->assertFalse($item['can_manage']);
            foreach (['approved', 'rejected'] as $decision) {
                $this->postJson('/api/staff/approvals/'.$item['type'].'/'.$item['id'], compact('decision'))->assertForbidden();
            }
            $this->postJson('/api/staff/dashboard/absence-cancellation-notification', [
                'type' => $item['type'], 'id' => $item['id'],
            ])->assertForbidden();
        }
    }

    public function test_direct_supervisor_keeps_decision_permissions_and_outsider_sees_no_requests(): void
    {
        $this->seedRequests();
        Sanctum::actingAs($this->supervisor('DIRECT'));
        $this->getJson('/api/staff/approvals')->assertOk()->assertJsonPath('pending_count', 4);
        Sanctum::actingAs($this->supervisor('OUTSIDER'));
        $this->getJson('/api/staff/approvals')->assertForbidden();
    }

    public function test_schedule_is_read_only_for_indirect_supervisor_and_writable_for_direct_supervisor(): void
    {
        DB::table('attendance_schedule_categories')->insert(['code' => 'P0', 'name' => 'Pagi']);
        $period = ['start_date' => '2026-06-03', 'end_date' => '2026-06-03'];
        Sanctum::actingAs($this->supervisor('INDIRECT'));
        $this->getJson('/api/staff/team-schedules?'.http_build_query($period))->assertOk()
            ->assertJsonPath('employees.0.can_manage', false);
        $this->getJson('/api/staff/team-schedules/employees/EMP001?'.http_build_query($period))->assertOk()
            ->assertJsonPath('employee.can_manage', false);
        $payload = [...$period, 'schedules' => [['date' => '2026-06-03', 'code' => 'P0']]];
        $this->putJson('/api/staff/team-schedules/employees/EMP001', $payload)->assertForbidden();
        $this->postJson('/api/staff/team-schedules/upload', $period)->assertForbidden();
        $this->assertDatabaseCount('employee_daily_schedules', 0);

        Sanctum::actingAs($this->supervisor('DIRECT'));
        $this->putJson('/api/staff/team-schedules/employees/EMP001', $payload)->assertOk();
        $this->assertDatabaseHas('employee_daily_schedules', ['karyawan_nik' => 'EMP001', 'schedule_code' => 'P0']);
    }

    public function test_bulk_upload_skips_indirect_subordinates_in_mixed_team(): void
    {
        Karyawan::create(['nik' => 'DIRECT-CHILD', 'nama_karyawan' => 'Direct Child', 'atasan_langsung_nik' => 'INDIRECT']);
        DB::table('attendance_schedule_categories')->insert(['code' => 'P0', 'name' => 'Pagi']);
        Sanctum::actingAs($this->supervisor('INDIRECT'));
        $file = UploadedFile::fake()->createWithContent('schedule.csv', "NIK,Nama,2026-06-03\nEMP001,Indirect,P0\nDIRECT-CHILD,Direct,P0\n");
        $this->post('/api/staff/team-schedules/upload', [
            'start_date' => '2026-06-03', 'end_date' => '2026-06-03', 'file' => $file,
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertDatabaseMissing('employee_daily_schedules', ['karyawan_nik' => 'EMP001']);
        $this->assertDatabaseHas('employee_daily_schedules', ['karyawan_nik' => 'DIRECT-CHILD']);
    }

    public function test_indirect_supervisor_can_view_active_contract_and_preview_document(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('contracts/test.pdf', '%PDF-test');
        $id = DB::table('t_kontrak_karyawan')->insertGetId([
            'nik' => 'EMP001', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'document' => 'contracts/test.pdf',
        ]);
        Sanctum::actingAs($this->supervisor('INDIRECT'));
        $this->getJson('/api/staff/contracts')->assertOk()->assertJsonPath('team_contracts.0.employee.nik', 'EMP001');
        $this->getJson('/api/staff/contracts/'.$id.'/pdf-preview')->assertOk();
        Sanctum::actingAs($this->supervisor('OUTSIDER'));
        $this->getJson('/api/staff/contracts/'.$id.'/pdf-preview')->assertNotFound();
    }

    public function test_indirect_supervisor_can_view_attendance_scoped_to_subordinates(): void
    {
        $this->mock(\App\Services\HrAttendanceReportService::class, function ($mock): void {
            $mock->shouldReceive('report')->once()->withArgs(function (array $filters): bool {
                return $filters['employee_niks'] === ['EMP001'];
            })->andReturn(['records' => [[
                'nik' => 'EMP001', 'name' => 'Employee', 'position' => 'Staff', 'department' => 'Operations',
                'days' => [[
                    'date' => '2026-06-02', 'status' => 'M', 'scan_in' => '08:00', 'scan_out' => '17:00',
                ]],
            ]]]);
        });
        Sanctum::actingAs($this->supervisor('INDIRECT'));
        $this->getJson('/api/staff/team-attendances?start_date=2026-06-02&end_date=2026-06-02')->assertOk()
            ->assertJsonPath('records.0.nik', 'EMP001')
            ->assertJsonPath('records.0.relationship', 'Bawahan Tidak Langsung');
    }
}
