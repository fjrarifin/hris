<?php

namespace Tests\Feature;

use App\Models\Karyawan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('m_karyawan');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('pin')->nullable();
            $table->string('nik', 30)->unique();
            $table->string('nama_karyawan', 150);
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('posisi_level')->nullable();
            $table->string('posisi_title')->nullable();
            $table->string('divisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('unit')->nullable();
            $table->string('nama_atasan_langsung')->nullable();
            $table->string('atasan_tidak_langsung')->nullable();
            $table->string('status_kontrak')->nullable();
            $table->date('join_date')->nullable();
            $table->date('start_date')->nullable();
            $table->decimal('durasi_kontrak')->nullable();
            $table->date('end_date')->nullable();
            $table->string('total_masa_kerja')->nullable();
            $table->string('no_hp')->nullable();
            $table->timestamp('phone_updated_at')->nullable();
            $table->string('email')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('no_ktp')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('npwp')->default(false);
            $table->string('no_npwp')->nullable();
            $table->string('status_pernikahan')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('nama_institusi')->nullable();
            $table->string('jurusan')->nullable();
            $table->string('nama_pasangan')->nullable();
            $table->unsignedSmallInteger('jumlah_anak')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('kontak_darurat_nama')->nullable();
            $table->string('kontak_darurat_hubungan')->nullable();
            $table->string('kontak_darurat_no_hp')->nullable();
            $table->string('account_name')->nullable();
            $table->string('bank')->nullable();
            $table->string('no_rekening')->nullable();
            $table->boolean('bpjs')->default(false);
            $table->string('no_bpjs')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_lists_and_shows_employee_data_from_database(): void
    {
        Karyawan::create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Budi Santoso',
            'jabatan' => 'HR Staff',
            'departement' => 'HRD',
        ]);

        Karyawan::create([
            'nik' => 'EMP002',
            'nama_karyawan' => 'Fajar Wijaya',
            'jabatan' => 'IT Staff',
            'departement' => 'IT',
        ]);

        $this->getJson('/api/employee?q=Fajar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nik', 'EMP002')
            ->assertJsonPath('data.0.nama_karyawan', 'Fajar Wijaya');

        $this->getJson('/api/employees?q=Budi')
            ->assertOk()
            ->assertJsonPath('data.0.nik', 'EMP001');

        $this->getJson('/api/employee/EMP002')
            ->assertOk()
            ->assertJsonPath('data.departement', 'IT');
    }

    public function test_it_creates_updates_and_deletes_an_employee(): void
    {
        $this->postJson('/api/employee', [
            'nik' => 'EMP003',
            'nama_karyawan' => 'Siti Aminah',
            'posisi_level' => 'Jr.',
            'posisi_title' => 'Staff',
            'divisi' => 'Business Partner',
            'npwp' => true,
            'bpjs' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.nik', 'EMP003')
            ->assertJsonPath('data.posisi', 'Jr. Staff');

        $this->assertDatabaseHas('m_karyawan', [
            'nik' => 'EMP003',
            'nama_karyawan' => 'Siti Aminah',
            'bpjs' => true,
        ]);

        $this->patchJson('/api/employee/EMP003', [
            'nama_karyawan' => 'Siti A. Aminah',
            'departement' => 'People Operations',
            'bpjs' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nama_karyawan', 'Siti A. Aminah')
            ->assertJsonPath('data.departement', 'People Operations')
            ->assertJsonPath('data.bpjs', false);

        $this->deleteJson('/api/employee/EMP003')
            ->assertNoContent();

        $this->assertDatabaseMissing('m_karyawan', ['nik' => 'EMP003']);
    }
}
