<?php

namespace Tests\Feature;

use App\Http\Services\WhatsAppService;
use App\Models\AttendanceScheduleCategory;
use App\Models\EmployeeDailySchedule;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Services\IncompleteAttendanceWhatsAppReport;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class IncompleteAttendanceWhatsAppReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('fingerspot_attendance_logs');
        Schema::dropIfExists('attendance_corrections');
        Schema::dropIfExists('employee_daily_schedules');
        Schema::dropIfExists('attendance_schedule_categories');
        Schema::dropIfExists('m_karyawan');

        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('pin')->nullable();
            $table->string('nama_karyawan');
            $table->string('jabatan')->nullable();
            $table->string('posisi')->nullable();
            $table->string('departement')->nullable();
            $table->string('divisi')->nullable();
            $table->string('no_hp')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_schedule_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('type');
            $table->boolean('is_workday');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_daily_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('karyawan_nik');
            $table->date('schedule_date');
            $table->unsignedBigInteger('schedule_category_id')->nullable();
            $table->string('schedule_code')->nullable();
            $table->timestamps();
        });

        Schema::create('fingerspot_attendance_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('pin');
            $table->dateTime('scan_date');
            $table->string('status_scan')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_corrections', function (Blueprint $table): void {
            $table->id();
            $table->string('nik');
            $table->date('attendance_date');
            $table->time('corrected_scan_in')->nullable();
            $table->time('corrected_scan_out')->nullable();
            $table->boolean('has_missing_attendance_form')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->timestamps();
        });

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.whatsapp.attendance_group_id', 'attendance-group');
        config()->set('services.whatsapp.attendance_warning_override_nik', null);
    }

    public function test_it_sends_scheduled_employees_with_incomplete_scans_to_attendance_group(): void
    {
        $work = AttendanceScheduleCategory::query()->create([
            'code' => 'WORK',
            'name' => 'Jadwal Kerja',
            'type' => 'work',
            'is_workday' => true,
        ]);

        $missingOut = $this->employee('EMP001', 'Ayu Pertiwi', 'PIN-1', 'Staff Finance', 'Finance');
        $missingIn = $this->employee('EMP002', 'Budi Setiawan', 'PIN-2', 'Supervisor', 'Sales');
        $complete = $this->employee('EMP003', 'Citra Utami', 'PIN-3', 'Staff', 'Marketing');
        $noScans = $this->employee('EMP004', 'Dian Kosong', 'PIN-4', 'Staff', 'Finance');

        foreach ([$missingOut, $complete, $noScans] as $employee) {
            EmployeeDailySchedule::query()->create([
                'karyawan_nik' => $employee->nik,
                'schedule_date' => '2026-05-25',
                'schedule_category_id' => $work->id,
                'schedule_code' => $work->code,
            ]);
        }

        $this->log('PIN-1', '2026-05-25 08:07:00', '0');
        $this->log('PIN-2', '2026-05-25 17:12:00', '1');
        $this->log('PIN-3', '2026-05-25 08:00:00', '0');
        $this->log('PIN-3', '2026-05-25 17:00:00', '1');

        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(
                    'attendance-group',
                    Mockery::on(fn (string $message): bool => str_contains($message, 'Ayu Pertiwi')
                        && str_contains($message, 'EMP001')
                        && str_contains($message, 'Staff Finance')
                        && str_contains($message, 'Finance')
                        && str_contains($message, 'Tidak scan pulang')
                        && str_contains($message, '08:07 WIB')
                        && str_contains($message, 'Budi Setiawan')
                        && str_contains($message, 'Tidak scan masuk')
                        && str_contains($message, '17:12 WIB')
                        && ! str_contains($message, 'Citra Utami')
                        && ! str_contains($message, 'Dian Kosong'))
                )
                ->andReturn(true);
        });

        $result = app(IncompleteAttendanceWhatsAppReport::class)
            ->sendForDate(Carbon::parse('2026-05-25'));

        $this->assertTrue($result['ok']);
    }

    public function test_it_sends_personal_warning_only_to_employee_with_phone_and_one_missing_scan(): void
    {
        $employee = $this->employee(
            'EMP001',
            'Ayu Pertiwi',
            'PIN-1',
            'Staff Finance',
            'Finance',
            '081234567890'
        );
        $this->employee('EMP002', 'Tanpa Nomor', 'PIN-2', 'Staff', 'Sales');

        $this->log('PIN-1', '2026-05-25 08:07:00', '0');
        $this->log('PIN-2', '2026-05-25 17:12:00', '1');

        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(
                    '081234567890',
                    Mockery::on(fn (string $message): bool => str_contains($message, 'PERINGATAN ABSENSI')
                        && str_contains($message, 'Ayu Pertiwi')
                        && str_contains($message, '25/05/2026')
                        && str_contains($message, 'Tidak scan pulang')
                        && str_contains($message, '08:07 WIB')
                        && str_contains($message, 'melapor kepada HRD'))
                )
                ->andReturn(true);
        });

        $result = app(IncompleteAttendanceWhatsAppReport::class)
            ->sendEmployeeWarningsForDate(Carbon::parse('2026-05-25'));

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['sent_count']);
        $this->assertSame(1, $result['skipped_count']);
        $this->assertSame($employee->no_hp, $result['notifications']->first()['phone']);
    }

    public function test_it_can_redirect_all_personal_warnings_to_one_employee_for_testing(): void
    {
        $this->employee('EMP001', 'Ayu Pertiwi', 'PIN-1', 'Staff Finance', 'Finance');
        $this->employee('EMP002', 'Budi Setiawan', 'PIN-2', 'Staff', 'Sales');
        $recipient = $this->employee(
            'HPP25120147',
            'Penerima Test',
            'PIN-TEST',
            'Staff',
            'IT',
            '081234567890'
        );

        $this->log('PIN-1', '2026-05-25 08:07:00', '0');
        $this->log('PIN-2', '2026-05-25 17:12:00', '1');
        config()->set('services.whatsapp.attendance_warning_override_nik', $recipient->nik);

        $this->mock(WhatsAppService::class, function ($mock) use ($recipient): void {
            $mock->shouldReceive('sendMessage')
                ->twice()
                ->with($recipient->no_hp, Mockery::type('string'))
                ->andReturn(true);
        });

        $result = app(IncompleteAttendanceWhatsAppReport::class)
            ->sendEmployeeWarningsForDate(Carbon::parse('2026-05-25'));

        $this->assertTrue($result['ok']);
        $this->assertSame(2, $result['sent_count']);
        $this->assertSame(0, $result['skipped_count']);
        $this->assertTrue($result['notifications']->every(
            fn (array $notification): bool => $notification['is_redirected']
                && $notification['recipient_nik'] === $recipient->nik
                && $notification['phone'] === $recipient->no_hp
        ));
    }

    private function employee(
        string $nik,
        string $name,
        string $pin,
        string $position,
        string $department,
        ?string $phone = null
    ): Karyawan {
        return Karyawan::query()->create([
            'nik' => $nik,
            'nama_karyawan' => $name,
            'pin' => $pin,
            'jabatan' => $position,
            'departement' => $department,
            'no_hp' => $phone,
        ]);
    }

    private function log(string $pin, string $scanDate, string $status): void
    {
        FingerspotAttendanceLog::query()->create([
            'pin' => $pin,
            'scan_date' => $scanDate,
            'status_scan' => $status,
        ]);
    }
}
