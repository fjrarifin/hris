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
            ['nama' => 'Gaji Pokok', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Tunjangan Jabatan', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Tunjangan Tidak Tetap', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Lembur', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Kekurangan Bulan Sebelumnya', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'THR', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Lain-lain', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Potongan Izin', 'type' => 'deduction', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Potongan Alpha', 'type' => 'deduction', 'input_mode' => 'calculated', 'is_active' => false],
            ['nama' => 'Potongan Kasbon', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Potongan Lain-lain', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'PPh21', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Tunjangan PPh21', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Tunjangan BPJS Kesehatan Karyawan', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => false],
            ['nama' => 'Tunjangan JHT Karyawan', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => false],
            ['nama' => 'Tunjangan JP Karyawan', 'type' => 'earning', 'input_mode' => 'calculated', 'is_active' => false],
            ['nama' => 'Potongan Sakit Tanpa Surat', 'type' => 'deduction', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Penyesuaian Pembulatan', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Potongan Pembulatan', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],

            // New components from CSV
            ['nama' => 'JKN Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'JHT Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'JP Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'JKK Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'JKM Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Pot. JKN Karyawan', 'type' => 'deduction', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Pot. JHT Karyawan', 'type' => 'deduction', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Pot. JP Karyawan', 'type' => 'deduction', 'input_mode' => 'calculated', 'is_active' => true],
            ['nama' => 'Nominal PIKET', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Training', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Kelebihan Gaji', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Pot. Denda Kehilangan Aset', 'type' => 'deduction', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Service', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
            ['nama' => 'Bonus', 'type' => 'earning', 'input_mode' => 'manual', 'is_active' => true],
        ];

        foreach ($components as $component) {
            PayrollComponent::updateOrCreate(
                ['nama' => $component['nama']],
                $component
            );
        }
    }
}
