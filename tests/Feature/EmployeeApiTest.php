<?php

namespace Tests\Feature;

use App\Models\FrontendMenu;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');

        Schema::dropIfExists('t_kontrak_karyawan');
        Schema::dropIfExists('m_karyawan');
        Schema::dropIfExists('frontend_menu_user_access');
        Schema::dropIfExists('frontend_menus');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('level')->default(2);
            $table->timestamps();
        });

        Schema::create('frontend_menus', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('path');
            $table->string('allowed_levels')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('frontend_menu_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('frontend_menu_id');
            $table->foreignId('user_id');
            $table->boolean('is_allowed');
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
            $table->string('status_karyawan')->nullable();
            $table->date('join_date')->nullable();
            $table->string('no_hp')->nullable();
            $table->timestamp('phone_updated_at')->nullable();
            $table->string('email')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('jenis_kelamin')->nullable();
            $table->string('golongan_darah')->nullable();
            $table->string('no_ktp')->nullable();
            $table->string('tempat_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->boolean('npwp')->default(false);
            $table->string('no_npwp')->nullable();
            $table->string('status_pajak')->nullable();
            $table->string('status_pernikahan')->nullable();
            $table->string('agama')->nullable();
            $table->string('kewarganegaraan')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('nama_institusi')->nullable();
            $table->string('jurusan')->nullable();
            $table->string('nama_pasangan')->nullable();
            $table->unsignedSmallInteger('jumlah_anak')->nullable();
            $table->string('nama_anak_1')->nullable();
            $table->string('nama_anak_2')->nullable();
            $table->string('nama_anak_3')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('kontak_darurat_nama')->nullable();
            $table->string('kontak_darurat_hubungan')->nullable();
            $table->string('kontak_darurat_no_hp')->nullable();
            $table->string('bank')->nullable();
            $table->string('no_rekening')->nullable();
            $table->boolean('bpjs')->default(false);
            $table->string('no_bpjs')->nullable();
            $table->timestamps();
        });

        Schema::create('t_kontrak_karyawan', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 30);
            $table->unsignedInteger('kontrak_ke');
            $table->string('jenis_kontrak')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('durasi_bulan')->nullable();
            $table->string('status_kontrak');
            $table->text('keterangan')->nullable();
            $table->string('document')->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();
        });

        FrontendMenu::create([
            'key' => 'employees',
            'label' => 'Karyawan',
            'path' => '/employees',
            'allowed_levels' => '2',
            'is_active' => true,
        ]);

        Sanctum::actingAs(User::create([
            'username' => 'hradmin',
            'name' => 'HR Administrator',
            'email' => 'hr@example.test',
            'password' => 'password',
            'level' => 2,
        ]));
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

        User::create([
            'username' => 'EMP002',
            'name' => 'Fajar Wijaya',
            'email' => 'fajar@example.test',
            'password' => 'password',
            'photo' => 'profile-photos/fajar.jpg',
            'level' => 3,
        ]);

        $this->getJson('/api/employee?q=Fajar')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nik', 'EMP002')
            ->assertJsonPath('data.0.nama_karyawan', 'Fajar Wijaya');

        $this->getJson('/api/employees')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nik', 'EMP001')
            ->assertJsonPath('data.0.name', 'Budi Santoso')
            ->assertJsonPath('data.0.position', 'HR Staff')
            ->assertJsonPath('data.0.department', 'HRD')
            ->assertJsonPath('data.1.photo_url', route('profile-photos.show', ['filename' => 'fajar.jpg']));

        $this->getJson('/api/employee/EMP002')
            ->assertOk()
            ->assertJsonPath('data.departement', 'IT')
            ->assertJsonPath('data.photo_url', route('profile-photos.show', ['filename' => 'fajar.jpg']))
            ->assertJsonCount(0, 'data.contracts');
    }

    public function test_it_creates_updates_and_deletes_an_employee(): void
    {
        Karyawan::create([
            'nik' => 'MGR001',
            'nama_karyawan' => 'Manager HR',
        ]);

        $this->post('/api/employee', [
            'nik' => 'EMP003',
            'nama_karyawan' => 'Siti Aminah',
            'jabatan' => 'HR Staff',
            'join_date' => '2025-12-25',
            'tanggal_lahir' => '1995-11-19',
            'posisi_level' => 'Jr.',
            'posisi_title' => 'Staff',
            'divisi' => 'Business Partner',
            'jenis_kontrak' => 'PKWT',
            'status_kontrak' => 'AKTIF',
            'start_date' => '2026-05-01',
            'end_date' => '2027-04-30',
            'keterangan_kontrak' => 'Kontrak awal',
            'status_pajak' => 'K/1',
            'nama_anak_1' => 'Anak Pertama',
            'nama_atasan_langsung' => 'Manager HR',
            'npwp' => true,
            'bpjs' => true,
            'document' => UploadedFile::fake()->create('kontrak-awal.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.nik', 'EMP003')
            ->assertJsonPath('data.posisi', 'Jr. Staff')
            ->assertJsonPath('data.join_date', '2025-12-25')
            ->assertJsonPath('data.tanggal_lahir', '1995-11-19')
            ->assertJsonPath('data.contracts.0.has_document', true);

        $this->assertDatabaseHas('m_karyawan', [
            'nik' => 'EMP003',
            'nama_karyawan' => 'Siti Aminah',
            'status_karyawan' => 'AKTIF',
            'status_pajak' => 'K/1',
            'status_pernikahan' => 'Menikah',
            'nama_anak_1' => 'Anak Pertama',
            'nama_atasan_langsung' => 'Manager HR',
            'bpjs' => true,
        ]);
        $this->assertDatabaseHas('t_kontrak_karyawan', [
            'nik' => 'EMP003',
            'status_kontrak' => 'AKTIF',
            'jenis_kontrak' => 'PKWT',
            'start_date' => '2026-05-01',
            'end_date' => '2027-04-30',
            'durasi_bulan' => 12,
        ]);
        Storage::disk('local')->assertExists(
            DB::table('t_kontrak_karyawan')->where('nik', 'EMP003')->value('document')
        );

        $this->patchJson('/api/employee/EMP003', [
            'nama_karyawan' => 'Siti A. Aminah',
            'departement' => 'People Operations',
            'join_date' => '2025-12-25',
            'tanggal_lahir' => '1995-11-19',
            'jenis_kontrak' => 'PKWTT',
            'status_kontrak' => 'NONAKTIF',
            'start_date' => '2026-05-01',
            'end_date' => '2027-10-31',
            'keterangan_kontrak' => 'Diakhiri',
            'bpjs' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.nama_karyawan', 'Siti A. Aminah')
            ->assertJsonPath('data.departement', 'People Operations')
            ->assertJsonPath('data.status_kontrak', 'NONAKTIF')
            ->assertJsonPath('data.status_karyawan', 'NONAKTIF')
            ->assertJsonPath('data.end_date', '2027-10-31')
            ->assertJsonPath('data.contracts.0.duration_months', 18)
            ->assertJsonPath('data.join_date', '2025-12-25')
            ->assertJsonPath('data.tanggal_lahir', '1995-11-19')
            ->assertJsonPath('data.bpjs', false);

        $this->assertDatabaseHas('m_karyawan', [
            'nik' => 'EMP003',
            'join_date' => '2025-12-25',
            'tanggal_lahir' => '1995-11-19',
        ]);
        $this->assertDatabaseHas('t_kontrak_karyawan', [
            'nik' => 'EMP003',
            'status_kontrak' => 'NONAKTIF',
            'jenis_kontrak' => 'PKWTT',
            'end_date' => '2027-10-31',
            'durasi_bulan' => 18,
        ]);

        $this->deleteJson('/api/employee/EMP003')
            ->assertNoContent();

        $this->assertDatabaseMissing('m_karyawan', ['nik' => 'EMP003']);
    }

    public function test_it_allows_hr_to_add_a_new_contract_without_document(): void
    {
        FrontendMenu::create([
            'key' => 'hr-contracts',
            'label' => 'Kontrak Karyawan',
            'path' => '/hr/contracts',
            'allowed_levels' => '2',
            'is_active' => true,
        ]);

        Karyawan::create([
            'nik' => 'EMP004',
            'nama_karyawan' => 'Rina Kontrak',
            'jabatan' => 'Staff',
        ]);
        Karyawan::create([
            'nik' => 'EMP005',
            'nama_karyawan' => 'Doni Kontrak',
            'jabatan' => 'Staff',
        ]);

        $payload = [
            'jenis_kontrak' => 'PKWT',
            'status_kontrak' => 'NONAKTIF',
            'start_date' => '2026-06-01',
            'end_date' => '2027-05-31',
            'keterangan' => 'Kontrak baru',
        ];

        $this->postJson('/api/hr/contracts/EMP004', $payload)
            ->assertCreated()
            ->assertJsonPath('data.contract_number', 1)
            ->assertJsonPath('data.status', 'NONAKTIF')
            ->assertJsonPath('data.has_document', false);

        $this->assertDatabaseHas('t_kontrak_karyawan', [
            'nik' => 'EMP004',
            'document' => null,
        ]);

        $this->post('/api/hr/contracts/EMP004', [
            ...$payload,
            'document' => UploadedFile::fake()->create('kontrak-terlalu-besar.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('document');

        $this->post('/api/hr/contracts/EMP005', [
            ...$payload,
            'status_kontrak' => 'AKTIF',
            'document' => UploadedFile::fake()->create('kontrak-rina.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.contract_number', 1)
            ->assertJsonPath('data.status', 'AKTIF')
            ->assertJsonPath('data.has_document', true);

        $document = DB::table('t_kontrak_karyawan')->where('nik', 'EMP005')->value('document');
        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document);

        $contractId = DB::table('t_kontrak_karyawan')->where('nik', 'EMP005')->value('id');

        $this->get('/api/hr/contracts/records/'.$contractId.'/pdf-preview')
            ->assertOk()
            ->assertJsonPath('mime_type', 'application/pdf')
            ->assertJsonPath('filename', 'Kontrak-EMP005-1.pdf');
    }

    public function test_it_sends_employee_userinfo_to_fingerspot_machine(): void
    {
        config()->set('fingerspot.base_url', 'https://developer.fingerspot.io/api');
        config()->set('fingerspot.api_token', 'test-token');
        config()->set('fingerspot.clouds', [
            ['id' => 'cloud-office', 'name' => 'Office'],
        ]);

        Karyawan::create([
            'nik' => 'EMP777',
            'pin' => 'PIN-777',
            'nama_karyawan' => 'User Fingerspot',
            'jabatan' => 'Staff',
        ]);

        Http::fake([
            'https://developer.fingerspot.io/api/set_userinfo' => Http::response([
                'success' => true,
                'message' => 'queued',
            ]),
        ]);

        $this->postJson('/api/employee/EMP777/fingerspot-userinfo', [
            'cloud_id' => 'cloud-office',
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.employee.pin', 'PIN-777')
            ->assertJsonPath('data.results.0.cloud.name', 'Office');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://developer.fingerspot.io/api/set_userinfo'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['cloud_id'] === 'cloud-office'
            && $request['data']['pin'] === 'PIN-777'
            && $request['data']['name'] === 'User Fingerspot'
            && $request['data']['privilege'] === '1'
            && $request['data']['template'] === '');
    }

    public function test_it_requires_employee_pin_before_sending_userinfo_to_fingerspot(): void
    {
        config()->set('fingerspot.api_token', 'test-token');
        config()->set('fingerspot.clouds', [
            ['id' => 'cloud-office', 'name' => 'Office'],
        ]);

        Karyawan::create([
            'nik' => 'EMP778',
            'nama_karyawan' => 'Tanpa PIN',
            'jabatan' => 'Staff',
        ]);

        Http::fake();

        $this->postJson('/api/employee/EMP778/fingerspot-userinfo', [
            'cloud_id' => 'cloud-office',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'PIN absensi karyawan belum diisi.');

        Http::assertNothingSent();
    }

    public function test_level_zero_can_receive_and_manage_its_allowed_frontend_menu(): void
    {
        FrontendMenu::query()->where('key', 'employees')->update([
            'allowed_levels' => '0,2',
        ]);

        FrontendMenu::create([
            'key' => 'menu-access',
            'label' => 'Akses Menu',
            'path' => '/access/menus',
            'allowed_levels' => '0',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        Sanctum::actingAs(User::create([
            'username' => 'itadmin',
            'name' => 'IT Administrator',
            'email' => 'it@example.test',
            'password' => 'password',
            'level' => 0,
        ]));

        $this->getJson('/api/navigation')
            ->assertOk()
            ->assertJsonFragment(['key' => 'employees'])
            ->assertJsonFragment(['key' => 'menu-access']);

        $this->getJson('/api/navigation/access')
            ->assertOk()
            ->assertJsonPath('menus.0.allowed_levels.0', 0)
            ->assertJsonPath('menus.0.allowed_levels.1', 2);
    }
}
