<?php

namespace Database\Seeders;

use App\Models\EmployeePayrollProfile;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Services\PayrollValidationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class May2026FajarArifinPayrollSeeder extends Seeder
{
    private const NIK = 'HPP25120147';

    private const PERIOD_START = '2026-04-25';

    private const PERIOD_END = '2026-05-24';

    public function run(): void
    {
        $components = PayrollComponent::query()->get()->keyBy('nama');
        $items = $this->items();

        $missingComponents = collect(array_keys($items))->diff($components->keys());
        if ($missingComponents->isNotEmpty()) {
            throw new RuntimeException('Komponen payroll belum tersedia: '.$missingComponents->implode(', '));
        }

        $earnings = $this->totalByType($items, 'earning');
        $deductions = $this->totalByType($items, 'deduction');
        $employerContribution = $this->totalByType($items, 'employer_contribution');
        $net = $earnings - $deductions;

        if ($net !== 6503935 || $employerContribution !== 577065) {
            throw new RuntimeException('Perhitungan payroll FAJAR ARIFIN tidak sesuai workbook Mei 2026.');
        }

        DB::transaction(function () use ($components, $items, $earnings, $deductions, $net): void {
            EmployeePayrollProfile::updateOrCreate(
                ['karyawan_nik' => self::NIK],
                [
                    'gaji_pokok' => 4106250,
                    'tunjangan_jabatan' => 1368750,
                    'tunjangan_tidak_tetap' => 1028935,
                    'bruto_man_power' => 7300000,
                    'payroll_group' => 'staff',
                    'dasar_bpjs' => 5475000,
                    'dasar_jp' => 5475000,
                    'rate_jkk_percent' => 0.54,
                    'is_active' => true,
                    'notes' => 'Seed contoh dari workbook Data Gaji Mei Untuk HRIS.xlsx, sheet PERHITUNGAN (2), baris 115.',
                ]
            );

            $payroll = Payroll::updateOrCreate(
                [
                    'karyawan_nik' => self::NIK,
                    'periode_start' => self::PERIOD_START,
                    'periode_end' => self::PERIOD_END,
                ],
                [
                    'hari_kerja' => 23,
                    'hadir' => 26,
                    'libur' => 0,
                    'izin' => 0,
                    'sakit_surat' => 0,
                    'sakit_tanpa_surat' => 0,
                    'tanpa_keterangan' => 0,
                    'cuti_tahunan' => 0,
                    'cuti_normatif' => 0,
                    'libur_nasional' => 0,
                    'ph' => 0,
                    'total_pendapatan' => $earnings,
                    'total_potongan' => $deductions,
                    'total_dibayarkan' => $net,
                    'approval_status' => 'draft',
                    'submitted_by' => null,
                    'submitted_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'approval_notes' => 'Seed contoh payroll Mei 2026 dari workbook HRIS.',
                    'is_locked' => false,
                    'locked_by' => null,
                    'locked_at' => null,
                ]
            );

            $payroll->items()->delete();

            foreach ($items as $name => $item) {
                $component = $components->get($name);

                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'component_id' => $component->id,
                    'nama_item' => $name,
                    'type' => $item['type'],
                    'amount' => $item['amount'],
                ]);
            }

            app(PayrollValidationService::class)->validateAndStore(
                $payroll->load(['karyawan', 'items.component'])
            );
        });
    }

    private function items(): array
    {
        return [
            'Gaji Pokok' => ['type' => 'earning', 'amount' => 4106250],
            'Tunjangan Jabatan' => ['type' => 'earning', 'amount' => 1368750],
            'Tunjangan Tidak Tetap' => ['type' => 'earning', 'amount' => 1028935],
            'Tunjangan BPJS Kesehatan Karyawan' => ['type' => 'earning', 'amount' => 54750],
            'Tunjangan JHT Karyawan' => ['type' => 'earning', 'amount' => 109500],
            'Tunjangan JP Karyawan' => ['type' => 'earning', 'amount' => 54750],
            'Pot. JKN Karyawan' => ['type' => 'deduction', 'amount' => 54750],
            'Pot. JHT Karyawan' => ['type' => 'deduction', 'amount' => 109500],
            'Pot. JP Karyawan' => ['type' => 'deduction', 'amount' => 54750],
            'JKN Perusahaan' => ['type' => 'employer_contribution', 'amount' => 219000],
            'JHT Perusahaan' => ['type' => 'employer_contribution', 'amount' => 202575],
            'JP Perusahaan' => ['type' => 'employer_contribution', 'amount' => 109500],
            'JKK Perusahaan' => ['type' => 'employer_contribution', 'amount' => 29565],
            'JKM Perusahaan' => ['type' => 'employer_contribution', 'amount' => 16425],
        ];
    }

    private function totalByType(array $items, string $type): int
    {
        return collect($items)
            ->where('type', $type)
            ->sum('amount');
    }
}
