<?php

namespace Database\Seeders;

use App\Models\EmployeePayrollProfile;
use App\Models\Karyawan;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use App\Services\PayrollValidationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class May2026PayrollWorkbookSeeder extends Seeder
{
    private const PERIOD_START = '2026-04-25';

    private const PERIOD_END = '2026-05-24';

    private const SHEET = 'PERHITUNGAN (2)';

    public function run(): void
    {
        $path = env('MAY_2026_PAYROLL_WORKBOOK', 'C:/Users/msi/Downloads/Data Gaji Mei Untuk HRIS.xlsx');
        if (! is_file($path)) {
            throw new RuntimeException("Workbook payroll Mei 2026 tidak ditemukan: {$path}");
        }

        $this->call(PayrollComponentSeeder::class);

        $sheet = IOFactory::load($path)->getSheetByName(self::SHEET);
        if (! $sheet) {
            throw new RuntimeException('Sheet payroll Mei 2026 tidak ditemukan.');
        }

        $components = PayrollComponent::query()->get()->keyBy('nama');
        $employees = Karyawan::query()->get()->keyBy('nik');
        $validation = app(PayrollValidationService::class);
        $summary = [
            'profiles' => 0,
            'payrolls' => 0,
            'missing_employees' => [],
            'incomplete_net' => [],
        ];

        DB::transaction(function () use ($sheet, $components, $employees, $validation, &$summary): void {
            for ($row = 5; $row <= $sheet->getHighestRow(); $row++) {
                $nik = trim((string) $sheet->getCell("B{$row}")->getCalculatedValue());
                if (! preg_match('/^[A-Z]{3}\d+$/', $nik)) {
                    continue;
                }

                $employee = $employees->get($nik);
                if (! $employee) {
                    $summary['missing_employees'][] = $this->rowLabel($sheet, $row);
                    continue;
                }

                $this->storeProfile($sheet, $row, $nik);
                $summary['profiles']++;

                $netValue = $sheet->getCell("BA{$row}")->getCalculatedValue();
                if ($netValue === null || $netValue === '') {
                    $summary['incomplete_net'][] = $this->rowLabel($sheet, $row);
                    continue;
                }

                $items = $this->items($sheet, $row);
                $net = $this->amount($netValue);
                $calculatedNet = $this->totalByType($items, 'earning') - $this->totalByType($items, 'deduction');
                $delta = $net - $calculatedNet;
                if ($delta > 0) {
                    $items['Penyesuaian Pembulatan'] = ['type' => 'earning', 'amount' => $delta];
                } elseif ($delta < 0) {
                    $items['Potongan Pembulatan'] = ['type' => 'deduction', 'amount' => abs($delta)];
                }

                $missingComponents = collect(array_keys($items))->diff($components->keys());
                if ($missingComponents->isNotEmpty()) {
                    throw new RuntimeException('Komponen payroll belum tersedia: '.$missingComponents->implode(', '));
                }

                $earnings = $this->totalByType($items, 'earning');
                $deductions = $this->totalByType($items, 'deduction');
                if ($earnings - $deductions !== $net) {
                    throw new RuntimeException("Rekonsiliasi NET gagal untuk {$nik} pada baris {$row}.");
                }

                $payroll = Payroll::updateOrCreate(
                    [
                        'karyawan_nik' => $nik,
                        'periode_start' => self::PERIOD_START,
                        'periode_end' => self::PERIOD_END,
                    ],
                    [
                        ...$this->attendance($sheet, $row),
                        'total_pendapatan' => $earnings,
                        'total_potongan' => $deductions,
                        'total_dibayarkan' => $net,
                        'approval_status' => 'draft',
                        'submitted_by' => null,
                        'submitted_at' => null,
                        'approved_by' => null,
                        'approved_at' => null,
                        'approval_notes' => 'Seed payroll Mei 2026 dari workbook HRIS.',
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

                $validation->validateAndStore($payroll->load(['karyawan', 'items.component']));
                $summary['payrolls']++;
            }
        });

        $this->command?->info('Seed payroll Mei 2026 selesai: '.json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    private function storeProfile($sheet, int $row, string $nik): void
    {
        EmployeePayrollProfile::updateOrCreate(
            ['karyawan_nik' => $nik],
            [
                'gaji_pokok' => $this->cellAmount($sheet, "X{$row}"),
                'tunjangan_jabatan' => $this->cellAmount($sheet, "Y{$row}"),
                'tunjangan_tidak_tetap' => $this->cellAmount($sheet, "AK{$row}"),
                'bruto_man_power' => max($this->cellAmount($sheet, "AS{$row}") - $this->cellAmount($sheet, "AY{$row}"), 0),
                'payroll_group' => str_contains(strtolower((string) $sheet->getCell("E{$row}")->getCalculatedValue()), 'operator') ? 'operator' : 'staff',
                'dasar_bpjs' => $this->cellAmount($sheet, "W{$row}"),
                'dasar_jp' => $this->cellAmount($sheet, "W{$row}"),
                'rate_jkk_percent' => 0.54,
                'is_active' => true,
                'notes' => "Seed dari workbook Data Gaji Mei Untuk HRIS.xlsx, sheet ".self::SHEET.", baris {$row}.",
            ]
        );
    }

    private function items($sheet, int $row): array
    {
        $mapping = [
            'Gaji Pokok' => ['earning', 'X'],
            'Tunjangan Jabatan' => ['earning', 'Y'],
            'Tunjangan Tidak Tetap' => ['earning', 'AK'],
            'Tunjangan BPJS Kesehatan Karyawan' => ['earning', 'AF'],
            'Tunjangan JHT Karyawan' => ['earning', 'AG'],
            'Tunjangan JP Karyawan' => ['earning', 'AH'],
            'Tunjangan PPh21' => ['earning', 'AY'],
            'Lembur' => ['earning', 'AM'],
            'Nominal PIKET' => ['earning', 'AN'],
            'Lain-lain' => ['earning', 'AO'],
            'Training' => ['earning', 'AP'],
            'THR' => ['earning', 'AQ'],
            'Kekurangan Bulan Sebelumnya' => ['earning', 'AR'],
            'Service' => ['earning', 'BB'],
            'Bonus' => ['earning', 'BD'],
            'Pot. JKN Karyawan' => ['deduction', 'AF'],
            'Pot. JHT Karyawan' => ['deduction', 'AG'],
            'Pot. JP Karyawan' => ['deduction', 'AH'],
            'Potongan Sakit Tanpa Surat' => ['deduction', 'AT'],
            'Potongan Izin' => ['deduction', 'AU'],
            'Potongan Kasbon' => ['deduction', 'AV'],
            'Kelebihan Gaji' => ['deduction', 'AW'],
            'Pot. Denda Kehilangan Aset' => ['deduction', 'AX'],
            'PPh 21' => ['deduction', 'AY'],
            'JKN Perusahaan' => ['employer_contribution', 'Z'],
            'JHT Perusahaan' => ['employer_contribution', 'AA'],
            'JP Perusahaan' => ['employer_contribution', 'AB'],
            'JKK Perusahaan' => ['employer_contribution', 'AC'],
            'JKM Perusahaan' => ['employer_contribution', 'AD'],
        ];

        return collect($mapping)
            ->map(fn (array $config) => [
                'type' => $config[0],
                'amount' => $this->cellAmount($sheet, "{$config[1]}{$row}"),
            ])
            ->filter(fn (array $item) => $item['amount'] > 0)
            ->all();
    }

    private function attendance($sheet, int $row): array
    {
        return [
            'hari_kerja' => $this->cellAmount($sheet, "U{$row}"),
            'hadir' => $this->cellAmount($sheet, "L{$row}"),
            'libur' => $this->cellAmount($sheet, "R{$row}"),
            'izin' => $this->cellAmount($sheet, "S{$row}"),
            'sakit_surat' => $this->cellAmount($sheet, "M{$row}"),
            'sakit_tanpa_surat' => $this->cellAmount($sheet, "Q{$row}"),
            'tanpa_keterangan' => $this->cellAmount($sheet, "O{$row}"),
            'cuti_tahunan' => 0,
            'cuti_normatif' => $this->cellAmount($sheet, "P{$row}"),
            'libur_nasional' => $this->cellAmount($sheet, "T{$row}"),
            'ph' => $this->cellAmount($sheet, "N{$row}"),
        ];
    }

    private function cellAmount($sheet, string $cell): int
    {
        return $this->amount($sheet->getCell($cell)->getCalculatedValue());
    }

    private function amount(mixed $value): int
    {
        return (int) round((float) ($value ?: 0));
    }

    private function totalByType(array $items, string $type): int
    {
        return (int) collect($items)->where('type', $type)->sum('amount');
    }

    private function rowLabel($sheet, int $row): array
    {
        return [
            'row' => $row,
            'nik' => trim((string) $sheet->getCell("B{$row}")->getCalculatedValue()),
            'name' => trim((string) $sheet->getCell("C{$row}")->getCalculatedValue()),
        ];
    }
}
