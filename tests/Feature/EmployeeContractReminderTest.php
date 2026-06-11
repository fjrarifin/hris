<?php

namespace Tests\Feature;

use App\Http\Services\WhatsAppService;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class EmployeeContractReminderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notifications');
        Schema::dropIfExists('t_kontrak_karyawan');
        Schema::dropIfExists('m_karyawan');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedTinyInteger('level')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->id();
            $table->string('nik', 30)->unique();
            $table->string('nama_karyawan', 150);
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('unit')->nullable();
            $table->string('status_karyawan')->nullable();
            $table->timestamps();
        });

        Schema::create('t_kontrak_karyawan', function (Blueprint $table): void {
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
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        config()->set('services.whatsapp.attendance_group_id', 'attendance-group');
    }

    public function test_contract_starting_within_one_month_marks_employee_active(): void
    {
        $this->mockWhatsApp()->shouldNotReceive('sendMessage');

        Karyawan::query()->create([
            'nik' => 'EMP001',
            'nama_karyawan' => 'Ayu Pertiwi',
            'status_karyawan' => 'NONAKTIF',
        ]);

        $this->contract('EMP001', '2026-06-24', '2026-12-31');

        Artisan::call('contracts:expire', ['--date' => '2026-06-11']);

        $this->assertSame('AKTIF', Karyawan::query()->where('nik', 'EMP001')->value('status_karyawan'));
    }

    public function test_it_sends_contract_expiry_reminder_to_hrd_and_attendance_whatsapp_group(): void
    {
        User::query()->create([
            'name' => 'HRD',
            'username' => 'hrd0001',
            'email' => 'hrd@example.test',
            'password' => 'secret',
            'level' => 2,
            'is_active' => true,
        ]);

        Karyawan::query()->create([
            'nik' => 'EMP002',
            'nama_karyawan' => 'Budi Setiawan',
            'jabatan' => 'Staff',
            'departement' => 'Operasional',
            'status_karyawan' => 'AKTIF',
        ]);

        $this->contract('EMP002', '2026-01-01', '2026-07-26');

        $this->mockWhatsApp()
            ->shouldReceive('sendMessage')
            ->once()
            ->withArgs(fn (string $recipient, string $message): bool => $recipient === 'attendance-group'
                && str_contains($message, 'Budi Setiawan')
                && str_contains($message, '45 hari'))
            ->andReturnTrue();

        Artisan::call('contracts:expire', ['--date' => '2026-06-11']);

        $notifications = User::query()->where('username', 'hrd0001')->firstOrFail()->notifications;
        $this->assertCount(1, $notifications);
        $this->assertSame('employee_contract_expiry_reminder', $notifications->first()->data['type']);
        $this->assertSame(45, $notifications->first()->data['days_before']);

        $this->assertDatabaseHas('notifications', [
            'type' => 'whatsapp',
            'notifiable_type' => 'whatsapp_group',
        ]);
    }

    private function mockWhatsApp()
    {
        $mock = Mockery::mock(WhatsAppService::class);
        $this->app->instance(WhatsAppService::class, $mock);

        return $mock;
    }

    private function contract(string $nik, string $startDate, string $endDate): void
    {
        \DB::table('t_kontrak_karyawan')->insert([
            'nik' => $nik,
            'kontrak_ke' => 1,
            'jenis_kontrak' => 'PKWT',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'durasi_bulan' => 6,
            'status_kontrak' => 'AKTIF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
