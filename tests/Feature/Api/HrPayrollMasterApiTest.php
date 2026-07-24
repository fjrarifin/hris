<?php

namespace Tests\Feature\Api;

use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\PayrollComponent;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrPayrollMasterApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['employee_payroll_profiles', 'payroll_components', 'm_karyawan', 'frontend_menu_user_access', 'frontend_menus', 'users'] as $table) {
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
            $table->string('departement')->nullable();
            $table->string('status_karyawan')->nullable();
            $table->boolean('bpjs')->default(false);
            $table->string('no_bpjs')->nullable();
            $table->timestamps();
        });
        Schema::create('employee_payroll_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik')->unique();
            $table->unsignedBigInteger('gaji_pokok')->default(0);
            $table->unsignedBigInteger('tunjangan_jabatan')->default(0);
            $table->unsignedBigInteger('tunjangan_tidak_tetap')->default(0);
            $table->decimal('rate_jkn_karyawan_percent', 5, 2)->default(1.00);
            $table->decimal('rate_jkn_perusahaan_percent', 5, 2)->default(4.00);
            $table->decimal('rate_jht_karyawan_percent', 5, 2)->default(2.00);
            $table->decimal('rate_jht_perusahaan_percent', 5, 2)->default(3.70);
            $table->decimal('rate_jp_karyawan_percent', 5, 2)->default(1.00);
            $table->decimal('rate_jp_perusahaan_percent', 5, 2)->default(2.00);
            $table->decimal('rate_jkk_percent', 5, 2)->default(0.54);
            $table->decimal('rate_jkm_percent', 5, 2)->default(0.30);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('payroll_components', function (Blueprint $table): void {
            $table->id();
            $table->string('nama');
            $table->string('type');
            $table->string('input_mode');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        FrontendMenu::query()->create([
            'key' => 'hr-payroll-master',
            'label' => 'Master Payroll',
            'path' => '/payroll/master',
            'allowed_levels' => '1,2',
        ]);
        Sanctum::actingAs(User::query()->create([
            'username' => 'HR001',
            'name' => 'HRD',
            'email' => 'hr@example.test',
            'password' => 'password',
            'level' => 2,
        ]));
    }

    public function test_it_stores_employee_payroll_master_and_marks_bpjs_employee_ready(): void
    {
        Karyawan::query()->create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Ayu',
            'bpjs' => true,
        ]);

        $this->putJson('/api/hr/payroll/master/EMP001', [
            'gaji_pokok' => 5000000,
            'tunjangan_jabatan' => 500000,
            'tunjangan_tidak_tetap' => 250000,
            'dasar_bpjs' => 5000000,
            'dasar_jp' => 5000000,
            'rate_jkk_percent' => 0.54,
            'is_active' => true,
            'notes' => 'Master awal',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_ready', true)
            ->assertJsonPath('data.profile.gaji_pokok', 5000000);

        $this->assertDatabaseHas('employee_payroll_profiles', [
            'karyawan_nik' => 'EMP001',
            'gaji_pokok' => 5000000,
        ]);
    }

    public function test_non_bpjs_employee_only_requires_basic_salary(): void
    {
        Karyawan::query()->create([
            'nik' => 'EMP002',
            'nama_karyawan' => 'Budi',
            'bpjs' => false,
        ]);

        $this->putJson('/api/hr/payroll/master/EMP002', [
            'gaji_pokok' => 4500000,
            'tunjangan_jabatan' => 0,
            'tunjangan_tidak_tetap' => 0,
            'dasar_bpjs' => 0,
            'dasar_jp' => 0,
            'rate_jkk_percent' => 0.54,
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_ready', true);
    }

    public function test_it_updates_component_category_without_touching_payroll_history(): void
    {
        $component = PayrollComponent::query()->create([
            'nama' => 'JKN Perusahaan',
            'type' => 'earning',
            'input_mode' => 'manual',
            'is_active' => true,
        ]);

        $this->putJson('/api/hr/payroll/master/components/'.$component->id, [
            'type' => 'employer_contribution',
            'input_mode' => 'calculated',
            'is_active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.type', 'employer_contribution')
            ->assertJsonPath('data.input_mode', 'calculated');
    }
}
