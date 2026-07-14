<?php

namespace Tests\Feature\Api;

use App\Http\Services\ApprovalNotificationService;
use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class StaffLeaveApiTest extends TestCase
{
    private User $employee;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'frontend_menu_user_access',
            'frontend_menus',
            'leave_requests',
            'leave_accruals',
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
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('unit')->nullable();
            $table->date('join_date')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_accruals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('nik')->nullable();
            $table->integer('year');
            $table->integer('month');
            $table->dateTime('accrued_at');
            $table->dateTime('expired_at');
            $table->unsignedInteger('days')->default(1);
            $table->boolean('is_used')->default(false);
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason')->nullable();
            $table->string('status');
            $table->text('reject_reason')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();
            $table->foreignId('manager_approved_by')->nullable();
            $table->foreignId('hr_approved_by')->nullable();
            $table->timestamp('second_manager_approved_at')->nullable();
            $table->foreignId('second_manager_approved_by')->nullable();
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

        $this->createMenu('staff-leave', '/staff/leave');

        $this->employee = $this->createEmployeeUser('EMP001', 'Karyawan Satu', 'PIN-001');

        Sanctum::actingAs($this->employee);
        Carbon::setTestNow('2026-06-02 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_employee_can_submit_annual_leave_without_balance_and_balance_can_go_negative(): void
    {
        $this->mock(ApprovalNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyManager')->once();
        });

        $this->postJson('/api/staff/leave', [
            'leave_type' => 'cuti_tahunan',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-03',
            'reason' => 'Istirahat',
        ])->assertCreated();

        $this->getJson('/api/staff/leave')
            ->assertOk()
            ->assertJsonPath('balance.total', 0)
            ->assertJsonPath('balance.available', -1);
    }

    private function createMenu(string $key, string $path): void
    {
        FrontendMenu::query()->create([
            'key' => $key,
            'label' => ucfirst(str_replace('-', ' ', $key)),
            'path' => $path,
            'icon' => 'heroicons-outline:document',
            'allowed_levels' => '3',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function createEmployeeUser(string $nik, string $name, string $pin): User
    {
        $employee = Karyawan::query()->create([
            'nik' => $nik,
            'pin' => $pin,
            'nama_karyawan' => $name,
            'nama_atasan_langsung' => null,
            'posisi_title' => 'Staff',
            'jabatan' => 'Staff',
            'posisi' => 'Staff',
            'departement' => 'Operations',
            'divisi' => 'Operations',
            'unit' => 'Ops',
            'join_date' => '2024-01-01',
            'email' => strtolower($nik).'@example.com',
        ]);

        $user = User::query()->create([
            'username' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'email' => $employee->email,
            'password' => bcrypt('password'),
            'level' => 3,
            'must_change_password' => false,
        ]);

        $menu = FrontendMenu::query()->where('key', 'staff-leave')->firstOrFail();
        $menu->users()->attach($user->id, ['is_allowed' => true]);

        return $user;
    }
}
