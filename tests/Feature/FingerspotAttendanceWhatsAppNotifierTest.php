<?php

namespace Tests\Feature;

use App\Http\Services\WhatsAppService;
use App\Models\FingerspotWebhookLog;
use App\Models\Karyawan;
use App\Services\FingerspotAttendanceWhatsAppNotifier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FingerspotAttendanceWhatsAppNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('m_karyawan');
        Schema::create('m_karyawan', function (Blueprint $table): void {
            $table->string('nik')->primary();
            $table->string('pin')->nullable();
            $table->string('nama_karyawan');
            $table->string('jabatan')->nullable();
            $table->timestamps();
        });

        config()->set('services.whatsapp.url', 'http://whatsapp.test');
        config()->set('services.whatsapp.device_id', 'device-id');
        config()->set('services.whatsapp.attendance_group_id', 'attendance-group');
    }

    public function test_it_does_not_send_attendance_notification_for_registered_pin(): void
    {
        Karyawan::query()->create([
            'nik' => 'EMP001',
            'pin' => 'PIN-001',
            'nama_karyawan' => 'Karyawan Terdaftar',
            'jabatan' => 'Staff',
        ]);

        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldNotReceive('sendMessage');
        });

        app(FingerspotAttendanceWhatsAppNotifier::class)->notify($this->webhookLog('PIN-001', '0'));
        app(FingerspotAttendanceWhatsAppNotifier::class)->notify($this->webhookLog('PIN-001', '1'));
    }

    public function test_it_sends_attendance_notification_for_unregistered_pin(): void
    {
        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(
                    'attendance-group',
                    Mockery::on(fn (string $message): bool => str_contains($message, 'PIN-404')
                        && str_contains($message, 'Absen Masuk')
                        && str_contains($message, 'belum terdaftar di HRIS'))
                )
                ->andReturn(true);
        });

        app(FingerspotAttendanceWhatsAppNotifier::class)->notify($this->webhookLog('PIN-404', '0'));
    }

    public function test_it_sends_scan_out_notification_for_unregistered_pin(): void
    {
        $this->mock(WhatsAppService::class, function ($mock): void {
            $mock->shouldReceive('sendMessage')
                ->once()
                ->with(
                    'attendance-group',
                    Mockery::on(fn (string $message): bool => str_contains($message, 'PIN-OUT')
                        && str_contains($message, 'Absen Keluar')
                        && str_contains($message, 'belum terdaftar di HRIS'))
                )
                ->andReturn(true);
        });

        app(FingerspotAttendanceWhatsAppNotifier::class)->notify($this->webhookLog('PIN-OUT', '1'));
    }

    private function webhookLog(string $pin, string $status): FingerspotWebhookLog
    {
        return new FingerspotWebhookLog([
            'pin' => $pin,
            'scan' => '2026-05-24 08:00:00',
            'status_scan' => $status,
        ]);
    }
}
