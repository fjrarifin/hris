<?php

namespace Tests\Feature\Api;

use App\Models\FrontendMenu;
use App\Models\MasterPositionTitle;
use App\Models\MasterDivision;
use App\Models\MasterDepartment;
use App\Models\MasterUnit;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrOrgStructureApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['master_position_titles', 'master_divisions', 'master_departments', 'master_units', 'frontend_menu_user_access', 'frontend_menus', 'users'] as $table) {
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

        Schema::create('master_position_titles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('master_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        FrontendMenu::query()->create([
            'key' => 'hr-master-positions',
            'label' => 'Master Posisi',
            'path' => '/hr/master/positions',
            'allowed_levels' => '2',
        ]);

        FrontendMenu::query()->create([
            'key' => 'hr-master-departments',
            'label' => 'Master Departemen',
            'path' => '/hr/master/departments',
            'allowed_levels' => '2',
        ]);
    }

    public function test_hrd_can_crud_master_departments(): void
    {
        $hrdUser = User::query()->create([
            'username' => 'HRD001',
            'name' => 'HRD Staff',
            'email' => 'hrd@example.test',
            'password' => 'password',
            'level' => 2,
        ]);

        Sanctum::actingAs($hrdUser);

        // 1. Create
        $response = $this->postJson('/api/hr/master-orgs/departments', [
            'name' => 'IT Department',
            'is_active' => true,
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('master_departments', [
            'name' => 'IT Department',
            'is_active' => true,
        ]);

        $deptId = $response->json('data.id');

        // 2. Update
        $response = $this->putJson("/api/hr/master-orgs/departments/{$deptId}", [
            'name' => 'IT Development',
            'is_active' => false,
        ]);
        $response->assertStatus(200);
        $this->assertDatabaseHas('master_departments', [
            'id' => $deptId,
            'name' => 'IT Development',
            'is_active' => false,
        ]);

        // 3. Delete
        $response = $this->deleteJson("/api/hr/master-orgs/departments/{$deptId}");
        $response->assertStatus(200);
        $this->assertDatabaseMissing('master_departments', [
            'id' => $deptId,
        ]);
    }

    public function test_employee_options_returns_active_entries(): void
    {
        MasterPositionTitle::query()->create(['name' => 'Operator', 'is_active' => true]);
        MasterPositionTitle::query()->create(['name' => 'NonActive Title', 'is_active' => false]);
        MasterDivision::query()->create(['name' => 'Business Partner', 'is_active' => true]);
        MasterDepartment::query()->create(['name' => 'HRBP', 'is_active' => true]);
        MasterUnit::query()->create(['name' => 'Zona 1', 'is_active' => true]);

        $hrdUser = User::query()->create([
            'username' => 'HRD001',
            'name' => 'HRD Staff',
            'email' => 'hrd@example.test',
            'password' => 'password',
            'level' => 2,
        ]);

        Sanctum::actingAs($hrdUser);

        $response = $this->getJson('/api/employee-options');
        $response->assertStatus(200);

        $response->assertJsonFragment(['position_titles' => ['Operator']]);
        $response->assertJsonFragment(['divisions' => ['Business Partner']]);
        $response->assertJsonFragment(['departments' => ['HRBP']]);
        $response->assertJsonFragment(['units' => ['Zona 1']]);
        
        $response->assertJsonMissingExact(['NonActive Title']);
    }
}
