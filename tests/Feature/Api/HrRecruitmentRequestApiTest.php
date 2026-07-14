<?php

namespace Tests\Feature\Api;

use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\RecruitmentRequest;
use App\Models\RecruitmentVacancy;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrRecruitmentRequestApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['recruitment_requests', 'recruitment_candidates', 'recruitment_vacancies', 'm_karyawan', 'frontend_menu_user_access', 'frontend_menus', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('level');
            $table->timestamps();
        });

        Schema::create('frontend_menus', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('path');
            $table->string('allowed_levels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('frontend_menu_user_access', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('frontend_menu_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_allowed');
        });

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('nama_karyawan');
            $table->string('jabatan')->nullable();
            $table->string('nama_atasan_langsung')->nullable();
            $table->string('atasan_langsung_nik', 30)->nullable();
            $table->string('atasan_tidak_langsung_nik', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_vacancies', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('recruitment_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('requester_nik', 30)->index();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->integer('quantity')->default(1);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('vacancy_id')->nullable();
            $table->text('hrd_notes')->nullable();
            $table->timestamps();
        });

        FrontendMenu::query()->create([
            'key' => 'staff-recruitment-requests',
            'label' => 'Pengajuan Rekrutmen',
            'path' => '/staff/recruitment/requests',
            'allowed_levels' => '3',
        ]);

        FrontendMenu::query()->create([
            'key' => 'hr-recruitment-requests',
            'label' => 'Persetujuan Lowongan',
            'path' => '/hr/recruitment/requests',
            'allowed_levels' => '2',
        ]);
    }

    public function test_manager_can_submit_recruitment_request(): void
    {
        $managerUser = User::query()->create([
            'username' => 'MGR001',
            'name' => 'Manager HR',
            'email' => 'mgr@example.test',
            'password' => 'password',
            'level' => 3,
        ]);

        Karyawan::query()->create([
            'nik' => 'MGR001',
            'nama_karyawan' => 'Manager HR',
            'jabatan' => 'Manager',
        ]);

        Sanctum::actingAs($managerUser);

        $response = $this->postJson('/api/staff/recruitment/requests', [
            'title' => 'Graphic Designer',
            'department' => 'Creative',
            'quantity' => 2,
            'description' => 'We need help for marketing designs.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('recruitment_requests', [
            'title' => 'Graphic Designer',
            'requester_nik' => 'MGR001',
            'status' => 'pending',
        ]);
    }

    public function test_hrd_can_approve_recruitment_request_and_create_new_vacancy(): void
    {
        $hrdUser = User::query()->create([
            'username' => 'HRD001',
            'name' => 'HRD Staff',
            'email' => 'hrd@example.test',
            'password' => 'password',
            'level' => 2,
        ]);

        $request = RecruitmentRequest::query()->create([
            'requester_nik' => 'MGR001',
            'title' => 'Android Developer',
            'department' => 'IT Mobile',
            'quantity' => 1,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($hrdUser);

        $response = $this->postJson("/api/hr/recruitment/requests/{$request->id}/decide", [
            'status' => 'approved',
            'hrd_notes' => 'Approved and vacancy opened.',
            'vacancy_link_mode' => 'new',
        ]);

        $response->assertStatus(200);
        $request->refresh();
        $this->assertEquals('approved', $request->status);
        $this->assertNotNull($request->vacancy_id);
        $this->assertDatabaseHas('recruitment_vacancies', [
            'id' => $request->vacancy_id,
            'title' => 'Android Developer',
            'status' => 'open',
        ]);
    }
}
