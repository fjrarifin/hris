<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COMPONENTS = [
        ['nama' => 'Gaji Pokok', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan Jabatan', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan Tidak Tetap', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Lembur', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Potongan Izin', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'Potongan Sakit Tanpa Surat', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'Pot. JKN Karyawan', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'Pot. JHT Karyawan', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'Pot. JP Karyawan', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'JKN Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
        ['nama' => 'JHT Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
        ['nama' => 'JP Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
        ['nama' => 'JKK Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
        ['nama' => 'JKM Perusahaan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::COMPONENTS as $component) {
            $exists = DB::table('payroll_components')->where('nama', $component['nama'])->exists();
            $values = $component + [
                'is_active' => true,
                'updated_at' => $now,
            ];

            if (! $exists) {
                DB::table('payroll_components')->insert($values + [
                    'created_at' => $now,
                ]);

                continue;
            }

            DB::table('payroll_components')
                ->where('nama', $component['nama'])
                ->update($values);
        }

        DB::table('payroll_components')
            ->where('nama', 'Potongan Alpha')
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Keep payroll component masters in place for historical payroll safety.
    }
};
