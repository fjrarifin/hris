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
            $table->string('posisi_title')->nullable();
            $table->string('departement')->nullable();
            $table->string('unit')->nullable();
            $table->string('divisi')->nullable();
            $table->string('no_hp')->nullable();
            $table->string('nama_atasan_langsung')->nullable();
            $table->string('atasan_langsung_nik', 30)->nullable();
            $table->string('atasan_tidak_langsung_nik', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('recruitment_vacancies', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->string('unit', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('recruitment_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('requester_nik', 30)->index();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->string('unit', 100)->nullable();
            $table->integer('quantity')->default(1);
            $table->text('description')->nullable();
            $table->string('hiring_type', 30)->default('new_hiring');
            $table->string('replaced_employee_nik', 30)->nullable();
            $table->string('replaced_employee_name', 150)->nullable();
            $table->string('replaced_employee_position', 150)->nullable();
            $table->string('employment_status', 30)->default('pkwt');
            $table->string('direct_report_nik', 30)->nullable();
            $table->string('direct_report_name', 150)->nullable();
            $table->json('subordinates')->nullable();
            $table->boolean('requires_gm')->default(false);
            $table->string('approval_step', 30)->default('manager');
            $table->string('manager_nik', 30)->nullable();
            $table->string('manager_name', 150)->nullable();
            $table->string('manager_status', 30)->default('pending');
            $table->timestamp('manager_approved_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->string('gm_nik', 30)->nullable();
            $table->string('gm_name', 150)->nullable();
            $table->string('gm_status', 30)->nullable();
            $table->timestamp('gm_approved_at')->nullable();
            $table->text('gm_notes')->nullable();
            $table->string('hrbp_nik', 30)->nullable();
            $table->string('hrbp_name', 150)->nullable();
            $table->string('hrbp_status', 30)->default('pending');
            $table->timestamp('hrbp_approved_at')->nullable();
            $table->text('hrbp_notes')->nullable();
            $table->string('approval_token', 64)->nullable();
            $table->timestamp('approval_token_expires_at')->nullable();
            $table->string('hiring_status', 30)->default('pending');
            $table->date('request_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('finished_date')->nullable();
            $table->string('pic_hiring_nik', 30)->nullable();
            $table->string('pic_hiring_name', 150)->nullable();
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
            'posisi_title' => 'Manager',
            'departement' => 'Creative',
            'unit' => 'Design',
        ]);

        Sanctum::actingAs($managerUser);

        $response = $this->postJson('/api/staff/recruitment/requests', [
            'title' => 'Graphic Designer',
            'department' => 'Creative',
            'unit' => 'Design',
            'quantity' => 2,
            'description' => 'We need help for marketing designs.',
            'hiring_type' => 'new_hiring',
            'employment_status' => 'pkwt',
            'requires_gm' => false,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('recruitment_requests', [
            'title' => 'Graphic Designer',
            'requester_nik' => 'MGR001',
            'hiring_type' => 'new_hiring',
            'employment_status' => 'pkwt',
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
            'unit' => 'Mobile Dev',
            'quantity' => 1,
            'status' => 'pending',
            'hiring_type' => 'new_hiring',
            'employment_status' => 'pkwt',
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
        $this->assertEquals('accepted', $request->hiring_status);
        $this->assertNotNull($request->vacancy_id);
        $this->assertDatabaseHas('recruitment_vacancies', [
            'id' => $request->vacancy_id,
            'title' => 'Android Developer',
            'status' => 'open',
        ]);
    }
}
