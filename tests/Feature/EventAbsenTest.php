<?php

namespace Tests\Feature;

use App\Models\AbsensiEvent;
use App\Models\EventAbsen;
use App\Models\EventAbsenParticipant;
use App\Models\Karyawan;
use App\Models\User;
use App\Services\EventAbsenWhatsAppService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventAbsenTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_create_and_list_event_absen_as_admin(): void
    {
        $admin = User::where('level', 0)->first() ?? User::factory()->create(['level' => 0]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/event-absen', [
            'nama_event' => 'Training K3 Batch 1',
            'deskripsi' => 'Pelatihan keselamatan kerja tahunan',
            'lokasi' => 'Ruang Meeting Lt. 2',
            'tanggal_mulai' => now()->subHour()->toDateTimeString(),
            'tanggal_selesai' => now()->addHours(5)->toDateTimeString(),
            'status' => 'aktif',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama_event', 'Training K3 Batch 1')
            ->assertJsonPath('data.lokasi', 'Ruang Meeting Lt. 2')
            ->assertJsonPath('data.slug', 'training-k3-batch-1')
            ->assertJsonPath('data.reminder_enabled', false);

        $listResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/event-absen?search=Ruang+Meeting');
        $listResponse->assertOk()
            ->assertJsonStructure(['data', 'meta', 'summary']);
    }

    public function test_can_create_event_with_participants_sends_nova_wa_and_restricts_public_attendance(): void
    {
        Http::fake([
            '*/send/message' => Http::response(['success' => true], 200),
        ]);

        $admin = User::where('level', 0)->first() ?? User::factory()->create(['level' => 0]);

        $invited = Karyawan::create([
            'nik' => 'EVTINV001',
            'nama_karyawan' => 'Peserta Terdaftar',
            'jabatan' => 'Staff IT',
            'divisi' => 'IT',
            'no_hp' => '081234567890',
        ]);

        $uninvited = Karyawan::create([
            'nik' => 'EVTOUT002',
            'nama_karyawan' => 'Bukan Peserta',
            'jabatan' => 'Staff HR',
            'divisi' => 'HR',
            'no_hp' => '081298765432',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/event-absen', [
            'nama_event' => 'Workshop HSSE Khusus',
            'lokasi' => 'Function Room 1',
            'deskripsi' => 'Wajib membawa helm proyek',
            'tanggal_mulai' => now()->subMinutes(10)->toDateTimeString(),
            'tanggal_selesai' => now()->addHours(2)->toDateTimeString(),
            'status' => 'aktif',
            'reminder_enabled' => true,
            'reminder_minutes_before' => 60,
            'participant_niks' => [$invited->nik],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lokasi', 'Function Room 1')
            ->assertJsonPath('data.reminder_enabled', true)
            ->assertJsonPath('data.reminder_minutes_before', 60)
            ->assertJsonPath('data.invited_participants_count', 1);

        $slug = $response->json('data.slug');
        $eventId = $response->json('data.id');

        $this->assertDatabaseHas('event_absen_participants', [
            'id_event_absen' => $eventId,
            'nik_karyawan' => $invited->nik,
        ]);

        // Uninvited employee must be blocked
        $blockedRes = $this->postJson("/api/public/event-absen/{$slug}/validasi-nik", [
            'nik' => $uninvited->nik,
        ]);
        $blockedRes->assertStatus(422)
            ->assertJsonPath('error_code', 'NOT_INVITED_PARTICIPANT');

        // Invited employee is allowed
        $allowedRes = $this->postJson("/api/public/event-absen/{$slug}/validasi-nik", [
            'nik' => $invited->nik,
        ]);
        $allowedRes->assertOk()
            ->assertJsonPath('data.nik', $invited->nik);

        // Test due reminder dispatch via Nova Agent
        $event = EventAbsen::findOrFail($eventId);
        $event->update([
            'tanggal_mulai' => now()->addMinutes(30),
            'tanggal_selesai' => now()->addHours(3),
            'reminder_minutes_before' => 60,
        ]);

        $summary = app(EventAbsenWhatsAppService::class)->sendDueReminders();
        $this->assertSame(1, $summary['sent']);
        $this->assertNotNull(
            EventAbsenParticipant::where('id_event_absen', $eventId)
                ->where('nik_karyawan', $invited->nik)
                ->value('reminder_sent_at')
        );
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
