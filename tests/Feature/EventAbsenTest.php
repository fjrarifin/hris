<?php

namespace Tests\Feature;

use App\Models\AbsensiEvent;
use App\Models\EventAbsen;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventAbsenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_create_and_list_event_absen_as_admin(): void
    {
        $admin = User::first() ?? User::factory()->create(['level' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/event-absen', [
            'nama_event' => 'Training K3 Batch 1',
            'deskripsi' => 'Pelatihan keselamatan kerja tahunan',
            'tanggal_mulai' => now()->subHour()->toDateTimeString(),
            'tanggal_selesai' => now()->addHours(5)->toDateTimeString(),
            'status' => 'aktif',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama_event', 'Training K3 Batch 1')
            ->assertJsonPath('data.slug', 'training-k3-batch-1');

        $listResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/event-absen?search=Training+K3');
        $listResponse->assertOk()
            ->assertJsonStructure(['data', 'meta', 'summary']);
    }

    public function test_public_flow_nik_validation_and_attendance_submission(): void
    {
        Storage::fake('public');

        $karyawan = Karyawan::first();
        if (! $karyawan) {
            $karyawan = Karyawan::create([
                'nik' => 'TEST9999',
                'nama_karyawan' => 'Budi Santoso Test',
                'jabatan' => 'Staff IT',
                'divisi' => 'Teknologi',
            ]);
        }

        $event = EventAbsen::create([
            'nama_event' => 'Gathering Internal 2026',
            'slug' => 'gathering-internal-2026-test',
            'tanggal_mulai' => now()->subHour(),
            'tanggal_selesai' => now()->addHours(4),
            'status' => 'aktif',
        ]);

        // 1. Check public show
        $showRes = $this->getJson("/api/public/event-absen/{$event->slug}");
        $showRes->assertOk()
            ->assertJsonPath('data.can_attend', true)
            ->assertJsonPath('data.nama_event', 'Gathering Internal 2026');

        // 2. Validate valid NIK (including lowercase test e.g. hpp...)
        $nikRes = $this->postJson("/api/public/event-absen/{$event->slug}/validasi-nik", [
            'nik' => strtolower($karyawan->nik),
        ]);
        $nikRes->assertOk()
            ->assertJsonPath('data.nik', $karyawan->nik);

        // 3. Submit attendance with base64 dummy 1x1 png
        $dummyBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $submitRes = $this->postJson("/api/public/event-absen/{$event->slug}/absen", [
            'nik' => $karyawan->nik,
            'photo' => $dummyBase64,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X)',
        ]);

        $submitRes->assertStatus(201)
            ->assertJsonPath('data.nik', $karyawan->nik);

        $this->assertDatabaseHas('absensi_event', [
            'id_event_absen' => $event->id,
            'nik_karyawan' => $karyawan->nik,
        ]);

        // 4. Try duplicate submit - must be rejected
        $dupRes = $this->postJson("/api/public/event-absen/{$event->slug}/validasi-nik", [
            'nik' => $karyawan->nik,
        ]);
        $dupRes->assertStatus(422)
            ->assertJsonPath('error_code', 'ALREADY_ATTENDED');
    }
}
