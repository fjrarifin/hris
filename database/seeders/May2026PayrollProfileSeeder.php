<?php

namespace Database\Seeders;

use App\Models\EmployeePayrollProfile;
use App\Models\Karyawan;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class May2026PayrollProfileSeeder extends Seeder
{
    private const SHEET = 'PERHITUNGAN (2)';

    public function run(): void
    {
        $path = env('MAY_2026_PAYROLL_WORKBOOK', 'C:/Users/msi/Downloads/Data Gaji Mei Untuk HRIS.xlsx');
        if (! is_file($path)) {
            throw new RuntimeException("Workbook payroll Mei 2026 tidak ditemukan: {$path}");
        }

        $sheet = IOFactory::load($path)->getSheetByName(self::SHEET);
        if (! $sheet) {
            throw new RuntimeException('Sheet payroll Mei 2026 tidak ditemukan.');
        }

        $employees = Karyawan::query()->pluck('nik')->flip();
        $summary = ['profiles' => 0, 'missing_employees' => []];

        for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
            $nik = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());
            if (! preg_match('/^[A-Z]{3}\d+$/', $nik)) {
                continue;
            }

            if (! $employees->has($nik)) {
                $summary['missing_employees'][] = $nik;
                continue;
            }

            EmployeePayrollProfile::updateOrCreate(
                ['karyawan_nik' => $nik],
                [
                    'gaji_pokok' => $this->cellAmount($sheet, "X{$row}"),
                    'tunjangan_jabatan' => $this->cellAmount($sheet, "Y{$row}"),
                    'tunjangan_tidak_tetap' => $this->cellAmount($sheet, "AK{$row}"),
                    'bruto_man_power' => max($this->cellAmount($sheet, "AS{$row}") - $this->cellAmount($sheet, "AY{$row}"), 0),
                    'payroll_group' => $this->payrollGroup($sheet, $row),
                    'dasar_bpjs' => $this->cellAmount($sheet, "W{$row}"),
                    'dasar_jp' => $this->cellAmount($sheet, "W{$row}"),
                    'rate_jkk_percent' => 0.54,
                    'is_active' => true,
                    'notes' => "Master payroll dari workbook Data Gaji Mei Untuk HRIS.xlsx, sheet ".self::SHEET.", baris {$row}.",
                ]
            );
            $summary['profiles']++;
        }

        $this->command?->info('Seed master payroll Mei 2026 selesai: '.json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    private function payrollGroup($sheet, int $row): string
    {
        $position = strtolower((string) $sheet->getCell("E{$row}")->getCalculatedValue());

        return str_contains($position, 'operator') ? 'operator' : 'staff';
    }

    private function cellAmount($sheet, string $cell): int
    {
        return (int) round((float) ($sheet->getCell($cell)->getCalculatedValue() ?: 0));
    }
}
