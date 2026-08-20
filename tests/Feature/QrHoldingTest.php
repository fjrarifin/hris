<?php

namespace Tests\Feature;

use App\Models\KaryawanHolding;
use App\Models\QrHoldingTransaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class QrHoldingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_valid_holding_employee_can_generate_gate_qr(): void
    {
        $karyawan = KaryawanHolding::create([
            'nik' => 'HLD26001',
            'nama' => 'Budi Holding Santoso',
            'jabatan' => 'Senior Auditor',
            'departemen' => 'Internal Audit',
            'perusahaan' => 'PT Hompimpa Global Holding',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/public/qr-holding/validate-and-generate', [
            'nik' => 'hld26001', // test case-insensitivity
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'karyawan' => [
                    'nik' => 'HLD26001',
                    'nama' => 'Budi Holding Santoso',
                    'jabatan' => 'Senior Auditor',
                    'perusahaan' => 'PT Hompimpa Global Holding',
                ],
            ]);

        $data = $response->json();
        $this->assertArrayHasKey('qr_payload', $data);
        $this->assertEquals('HLD26001', $data['qr_payload']['m']);
        $this->assertEquals([[4, 100, 374]], $data['qr_payload']['x']);

        $this->assertDatabaseHas('t_qr_holding', [
            'nik' => 'HLD26001',
            'nama' => 'Budi Holding Santoso',
            'm_karyawan_holding_id' => $karyawan->id,
        ]);
    }

    public function test_invalid_nik_returns_404(): void
    {
        $response = $this->postJson('/api/public/qr-holding/validate-and-generate', [
            'nik' => 'NONEXISTENT99',
        ]);

        $response->assertStatus(404);
    }

    public function test_inactive_employee_returns_404(): void
    {
        KaryawanHolding::create([
            'nik' => 'HLD_INACTIVE',
            'nama' => 'Karyawan Resigned',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/public/qr-holding/validate-and-generate', [
            'nik' => 'HLD_INACTIVE',
        ]);

        $response->assertStatus(404);
    }

    public function test_empty_nik_returns_validation_error(): void
    {
        $response = $this->postJson('/api/public/qr-holding/validate-and-generate', [
            'nik' => '',
        ]);

        $response->assertStatus(422);
    }
}
