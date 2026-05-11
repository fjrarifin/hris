<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PayrollComponent;

class PayrollComponentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $components = [
            // Existing ones (to ensure they exist)
            ['nama' => 'Gaji Pokok', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Tunjangan Jabatan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Tunjangan Tidak Tetap', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Lembur', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Kekurangan Bulan Sebelumnya', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'THR', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Lain-lain', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Potongan Izin', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Potongan Kasbon', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Potongan Lain-lain', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'PPh21', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Tunjangan BPJS Kesehatan Karyawan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Tunjangan JHT Karyawan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Tunjangan JP Karyawan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Potongan Sakit Tanpa Surat', 'type' => 'deduction', 'is_active' => true],

            // New components from CSV
            ['nama' => 'JKN Perusahaan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'JHT Perusahaan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'JP Perusahaan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'JKK Perusahaan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'JKM Perusahaan', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Pot. JKN Karyawan', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Pot. JHT Karyawan', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Pot. JP Karyawan', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Nominal PIKET', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Training', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Kelebihan Gaji', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Pot. Denda Kehilangan Aset', 'type' => 'deduction', 'is_active' => true],
            ['nama' => 'Service', 'type' => 'earning', 'is_active' => true],
            ['nama' => 'Bonus', 'type' => 'earning', 'is_active' => true],
        ];

        foreach ($components as $component) {
            PayrollComponent::updateOrCreate(
                ['nama' => $component['nama']],
                $component
            );
        }
    }
}
