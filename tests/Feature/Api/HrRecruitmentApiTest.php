<?php

namespace Tests\Feature\Api;

use App\Models\FrontendMenu;
use App\Models\RecruitmentVacancy;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrRecruitmentApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach (['recruitment_candidates', 'recruitment_vacancies', 'frontend_menu_user_access', 'frontend_menus', 'users'] as $table) {
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

        Schema::create('recruitment_vacancies', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'open', 'closed'])->default('draft');
            $table->timestamps();
        });

        Schema::create('recruitment_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vacancy_id')->nullable();
            $table->string('name', 150);
            $table->string('email', 100);
            $table->string('phone', 30)->nullable();
            $table->string('resume_path', 255)->nullable();
            $table->enum('status', ['applied', 'screening', 'interview', 'offered', 'hired', 'rejected'])->default('applied');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        FrontendMenu::query()->create([
            'key' => 'hr-recruitment-vacancies',
            'label' => 'Lowongan Kerja',
            'path' => '/hr/recruitment/vacancies',
            'allowed_levels' => '2',
        ]);

        FrontendMenu::query()->create([
            'key' => 'hr-recruitment-candidates',
            'label' => 'Pipeline Pelamar',
            'path' => '/hr/recruitment/candidates',
            'allowed_levels' => '2',
        ]);

        Sanctum::actingAs(User::query()->create([
            'username' => 'HR001',
            'name' => 'HRD Tester',
            'email' => 'hrd@example.test',
            'password' => 'password',
            'level' => 2,
        ]));
    }

    public function test_it_can_create_a_vacancy(): void
    {
        $response = $this->postJson('/api/hr/recruitment/vacancies', [
            'title' => 'Senior QA Engineer',
            'department' => 'IT',
            'description' => 'Test descriptions here.',
            'status' => 'open',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.title', 'Senior QA Engineer');
        $this->assertDatabaseHas('recruitment_vacancies', ['title' => 'Senior QA Engineer']);
    }

    public function test_it_can_create_a_candidate(): void
    {
        $vacancy = RecruitmentVacancy::query()->create([
            'title' => 'Product Manager',
            'status' => 'open',
        ]);

        $response = $this->postJson('/api/hr/recruitment/candidates', [
            'vacancy_id' => $vacancy->id,
            'name' => 'John Doe',
            'email' => 'john@example.test',
            'phone' => '0812345678',
            'status' => 'applied',
            'notes' => 'Looking good candidate.',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.name', 'John Doe');
        $this->assertDatabaseHas('recruitment_candidates', ['name' => 'John Doe']);
    }

    public function test_it_can_upload_candidate_resume(): void
    {
        $candidate = RecruitmentCandidate::query()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.test',
            'status' => 'screening',
        ]);

        $file = UploadedFile::fake()->create('cv.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/hr/recruitment/candidates/{$candidate->id}/upload-resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200);
        $candidate->refresh();
        $this->assertNotNull($candidate->resume_path);
        Storage::disk('local')->assertExists($candidate->resume_path);
    }
}
