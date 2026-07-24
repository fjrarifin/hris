<?php

namespace App\Services;

use App\Models\EmployeePayrollProfile;
use App\Models\EmployeeExtraOff;
use App\Models\Karyawan;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculationService
{
    private const FORMULA_VERSION = 'payroll-business-rules-2026-06-03';

    private const REQUIRED_COMPONENTS = [
        ['nama' => 'Gaji Pokok', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan Jabatan', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan Tidak Tetap', 'type' => 'earning', 'input_mode' => 'manual'],
        ['nama' => 'Lembur', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Kekurangan bulan sebelumnya', 'type' => 'earning', 'input_mode' => 'manual'],
        ['nama' => 'Lain-lain', 'type' => 'earning', 'input_mode' => 'manual'],
        ['nama' => 'Potongan Sakit Tanpa Surat', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'Potongan Izin', 'type' => 'deduction', 'input_mode' => 'calculated'],
        ['nama' => 'Potongan Kasbon', 'type' => 'deduction', 'input_mode' => 'manual'],
        ['nama' => 'Potongan Lain-lain', 'type' => 'deduction', 'input_mode' => 'manual'],
        ['nama' => 'Potongan Denda Kehilangan Aset', 'type' => 'deduction', 'input_mode' => 'manual'],
        ['nama' => 'Kelebihan Gaji', 'type' => 'deduction', 'input_mode' => 'manual'],
        ['nama' => 'Tunjangan BPJS Kesehatan Karyawan', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan JHT Karyawan', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan JP Karyawan', 'type' => 'earning', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan JKK Karyawan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
        ['nama' => 'Tunjangan JKM Karyawan', 'type' => 'employer_contribution', 'input_mode' => 'calculated'],
        ['nama' => 'PPh 21', 'type' => 'deduction', 'input_mode' => 'manual'],
    ];

    public function __construct(
        private readonly PayrollAttendanceReadinessService $readinessService,
        private readonly PayrollValidationService $validationService,
        private readonly PayrollPeriodService $periodService
    ) {
    }

    public function preview(array $filters): array
    {
        $filters = $this->periodService->normalizeFilters($filters);
        $audit = $this->readinessService->audit($filters);
        $employees = Karyawan::query()
            ->with('payrollProfile')
            ->whereIn('nik', $audit['records']->pluck('nik'))
            ->get()
            ->keyBy('nik');

        $records = $audit['records']->filter(function (array $attendance) {
            return ($attendance['total_hari_masuk'] ?? 0) > 0;
        })->map(function (array $attendance) use ($employees): array {
            $employee = $employees->get($attendance['nik']);
            $profile = $employee?->payrollProfile;
            $errors = [];

            if (! $profile) {
                $errors[] = 'Master payroll belum tersedia.';
            } elseif (! $profile->is_active) {
                $errors[] = 'Master payroll nonaktif.';
            } elseif ($profile->gaji_pokok <= 0) {
                $errors[] = 'Gaji pokok belum tersedia.';
            } elseif ($profile->bruto_man_power <= 0) {
                $errors[] = 'Bruto man power belum tersedia.';
            }

            return [
                ...$attendance,
                'is_attendance_ready' => $attendance['can_submit'],
                'can_generate' => $errors === [],
                'calculation_errors' => $errors,
                'calculation' => $errors === [] ? $this->calculate($profile, (bool) $employee->bpjs, $attendance) : null,
            ];
        })->values();

        return [
            'filters' => $audit['filters'],
            'summary' => [
                ...$audit['summary'],
                'can_generate' => $records->where('can_generate', true)->count(),
                'master_incomplete' => $records->where('can_generate', false)->count(),
                'total_net_estimation' => $records->sum('calculation.take_home_pay'),
                'total_gross' => $records->sum('calculation.gross_salary'),
                'total_lembur' => $records->sum(function ($r) {
                    $lembur = collect($r['calculation']['items'] ?? [])->firstWhere('name', 'Lembur');
                    return $lembur ? $lembur['amount'] : 0;
                }),
                'total_bpjs_perusahaan' => $records->sum('calculation.employer_contribution'),
                'total_bpjs_karyawan' => $records->sum('calculation.employee_bpjs_contribution'),
            ],
            'can_submit' => $audit['can_submit'],
            'records' => $records,
        ];
    }

    public function generate(array $filters): array
    {
        $preview = $this->preview($filters);
        $this->ensureRequiredComponents();
        $components = PayrollComponent::query()->where('is_active', true)->get()->keyBy('nama');
        $result = ['generated' => 0, 'skipped' => []];

        DB::transaction(function () use ($preview, $components, &$result): void {
            foreach ($preview['records'] as $record) {
                if (! $record['can_generate']) {
                    $result['skipped'][] = ['nik' => $record['nik'], 'errors' => $record['calculation_errors']];
                    continue;
                }

                $existing = Payroll::query()
                    ->where('karyawan_nik', $record['nik'])
                    ->whereDate('periode_start', $preview['filters']['start_date'])
                    ->whereDate('periode_end', $preview['filters']['end_date'])
                    ->first();

                if ($existing && $existing->approval_status !== 'draft') {
                    $result['skipped'][] = ['nik' => $record['nik'], 'errors' => ['Payroll bukan draft dan tidak boleh ditimpa.']];
                    continue;
                }

                $calculation = $record['calculation'];
                $payroll = Payroll::updateOrCreate(
                    [
                        'karyawan_nik' => $record['nik'],
                        'periode_start' => $preview['filters']['start_date'],
                        'periode_end' => $preview['filters']['end_date'],
                    ],
                    [
                        'hari_kerja' => $record['periode_hari_kerja'],
                        'hadir' => $record['present_days'],
                        'libur' => 0,
                        'izin' => $record['permission_days'],
                        'sakit_surat' => $record['sick_with_document_days'],
                        'sakit_tanpa_surat' => $record['sick_without_document_days'],
                        'tanpa_keterangan' => 0,
                        'cuti_tahunan' => $record['leave_days'],
                        'cuti_normatif' => 0,
                        'libur_nasional' => $record['period_public_holidays'],
                        'ph' => $record['ph_days'],
                        'basic_salary' => $calculation['basic_salary'],
                        'bruto_man_power' => $calculation['bruto_man_power'],
                        'total_hari_masuk' => $calculation['total_hari_masuk'],
                        'extra_off_days' => $calculation['extra_off_days'],
                        'tunjangan_tidak_tetap_full' => $calculation['tunjangan_tidak_tetap_full'],
                        'formula_version' => self::FORMULA_VERSION,
                        'total_pendapatan' => $calculation['gross_salary'],
                        'total_potongan' => $calculation['total_deduction'],
                        'total_dibayarkan' => $calculation['take_home_pay'],
                        'approval_status' => 'draft',
                        'approval_notes' => 'Draft dibuat dari master payroll dan absensi HRIS.',
                    ]
                );

                $payroll->items()->delete();
                foreach ($calculation['items'] as $item) {
                    $component = $components->get($item['name']);
                    if (! $component) {
                        throw new \RuntimeException("Komponen payroll belum tersedia: {$item['name']}");
                    }

                    PayrollItem::create([
                        'payroll_id' => $payroll->id,
                        'component_id' => $component->id,
                        'nama_item' => $item['name'],
                        'type' => $item['type'],
                        'amount' => $item['amount'],
                    ]);
                }

                EmployeeExtraOff::updateOrCreate(
                    [
                        'karyawan_nik' => $record['nik'],
                        'periode_start' => $preview['filters']['start_date'],
                        'periode_end' => $preview['filters']['end_date'],
                    ],
                    [
                        'days' => $calculation['extra_off_days'],
                        'source' => 'payroll',
                        'notes' => 'Saldo extra off otomatis dari generate payroll.',
                    ]
                );

                $this->validationService->validateAndStore($payroll->load(['karyawan', 'items.component']));
                $result['generated']++;
            }
        });

        return [...$result, 'preview' => $preview];
    }

    public function calculate(EmployeePayrollProfile $profile, bool $bpjsActive, array $attendance): array
    {
        $periodWorkdays = max((int) ($attendance['periode_hari_kerja'] ?? $attendance['scheduled_workdays'] ?? 1), 1);
        $totalHariMasuk = (int) ($attendance['total_hari_masuk'] ?? 0);
        $paidHariMasuk = min($totalHariMasuk, $periodWorkdays);
        $permissionDays = (int) ($attendance['permission_days'] ?? 0);
        $sickWithoutDocumentDays = (int) ($attendance['sick_without_document_days'] ?? 0);
        $alphaDays = 0;
        $extraOffDays = max($totalHariMasuk - $periodWorkdays, 0);
        $basicSalary = (int) $profile->gaji_pokok + (int) $profile->tunjangan_jabatan;
        $bpjsEmployee = $bpjsActive ? $this->bpjsEmployee($basicSalary, $profile) : ['jkn' => 0, 'jht' => 0, 'jp' => 0];
        $bpjsCompany = $bpjsActive ? $this->bpjsCompany($basicSalary, $profile) : ['jkn' => 0, 'jht' => 0, 'jp' => 0, 'jkk' => 0, 'jkm' => 0];
        $totalBpjsEmployee = array_sum($bpjsEmployee);
        $totalBpjsCompany = array_sum($bpjsCompany);
        $tttFull = max(
            (int) $profile->bruto_man_power
                - (int) $profile->gaji_pokok
                - (int) $profile->tunjangan_jabatan
                - $totalBpjsCompany
                - $totalBpjsEmployee,
            0
        );
        $tttComponent = PayrollComponent::query()->where('nama', 'Tunjangan Tidak Tetap')->first();
        $isTttManual = $tttComponent ? ($tttComponent->input_mode === 'manual') : true;

        $items = collect([
            $this->item('Gaji Pokok', 'earning', $profile->gaji_pokok),
            $this->item('Tunjangan Jabatan', 'earning', $profile->tunjangan_jabatan),
            $this->item('Lembur', 'earning', round(($profile->gaji_pokok / 173) * (((int) $attendance['overtime_minutes']) / 60) * 1.5)),
            $this->item('Potongan Izin', 'deduction', $permissionDays * $tttDailyRate),
            $this->item('Potongan Sakit Tanpa Surat', 'deduction', $sickWithoutDocumentDays * $tttDailyRate),
        ]);

        if (! $isTttManual) {
            $items->push($this->item('Tunjangan Tidak Tetap', 'earning', $tttProrata));
        }

        if ($bpjsActive) {
            $bpjsItems = [
                // BPJS Employee items act as Net Neutral (added as earning, then deducted as deduction)
                ['Tunjangan BPJS Kesehatan Karyawan', 'earning', $bpjsEmployee['jkn']],
                ['Tunjangan JHT Karyawan', 'earning', $bpjsEmployee['jht']],
                ['Tunjangan JP Karyawan', 'earning', $bpjsEmployee['jp']],
                ['Tunjangan BPJS Kesehatan Karyawan', 'deduction', $bpjsEmployee['jkn']],
                ['Tunjangan JHT Karyawan', 'deduction', $bpjsEmployee['jht']],
                ['Tunjangan JP Karyawan', 'deduction', $bpjsEmployee['jp']],
                // Employer contribution items
                ['Tunjangan BPJS Kesehatan Karyawan', 'employer_contribution', $bpjsCompany['jkn']],
                ['Tunjangan JHT Karyawan', 'employer_contribution', $bpjsCompany['jht']],
                ['Tunjangan JP Karyawan', 'employer_contribution', $bpjsCompany['jp']],
                ['Tunjangan JKK Karyawan', 'employer_contribution', $bpjsCompany['jkk']],
                ['Tunjangan JKM Karyawan', 'employer_contribution', $bpjsCompany['jkm']],
            ];
            $items = $items->concat(collect($bpjsItems)->map(fn (array $item) => $this->item(...$item)));
        }

        $items = $items->filter(fn (array $item) => $item['amount'] > 0)->values();
        $gross = $this->totalByType($items, 'earning');
        $deductions = $this->totalByType($items, 'deduction');
        $employer = $this->totalByType($items, 'employer_contribution');

        return [
            'formula_version' => self::FORMULA_VERSION,
            'basic_salary' => $basicSalary,
            'bruto_man_power' => (int) $profile->bruto_man_power,
            'periode_hari_kerja' => $periodWorkdays,
            'total_hari_masuk' => $totalHariMasuk,
            'paid_hari_masuk' => $paidHariMasuk,
            'alpha_days' => $alphaDays,
            'extra_off_days' => $extraOffDays,
            'tunjangan_tidak_tetap_full' => (int) round($tttFull),
            'daily_rate' => $tttDailyRate,
            'gross_salary' => $gross,
            'total_deduction' => $deductions,
            'net_deduction' => $deductions,
            'take_home_pay' => $gross - $deductions,
            'employer_contribution' => $employer,
            'employee_bpjs_contribution' => $totalBpjsEmployee,
            'company_cost' => $gross - $deductions + $employer,
            'items' => $items->all(),
        ];
    }

    private function bpjsEmployee(int $basicSalary, ?EmployeePayrollProfile $profile = null): array
    {
        $jknRate = (float) ($profile?->rate_jkn_karyawan_percent ?? 1.00);
        $jhtRate = (float) ($profile?->rate_jht_karyawan_percent ?? 2.00);
        $jpRate = (float) ($profile?->rate_jp_karyawan_percent ?? 1.00);

        return [
            'jkn' => (int) round($basicSalary * ($jknRate / 100)),
            'jht' => (int) round($basicSalary * ($jhtRate / 100)),
            'jp' => (int) round($basicSalary * ($jpRate / 100)),
        ];
    }

    private function bpjsCompany(int $basicSalary, ?EmployeePayrollProfile $profile = null): array
    {
        $jknRate = (float) ($profile?->rate_jkn_perusahaan_percent ?? 4.00);
        $jhtRate = (float) ($profile?->rate_jht_perusahaan_percent ?? 3.70);
        $jpRate = (float) ($profile?->rate_jp_perusahaan_percent ?? 2.00);
        $jkkRate = (float) ($profile?->rate_jkk_percent ?? 0.54);
        $jkmRate = (float) ($profile?->rate_jkm_percent ?? 0.30);

        return [
            'jkn' => (int) round($basicSalary * ($jknRate / 100)),
            'jht' => (int) round($basicSalary * ($jhtRate / 100)),
            'jp' => (int) round($basicSalary * ($jpRate / 100)),
            'jkk' => (int) round($basicSalary * ($jkkRate / 100)),
            'jkm' => (int) round($basicSalary * ($jkmRate / 100)),
        ];
    }

    private function item(string $name, string $type, int|float $amount): array
    {
        return compact('name', 'type') + ['amount' => (int) round($amount)];
    }

    private function totalByType(Collection $items, string $type): int
    {
        return (int) $items->where('type', $type)->sum('amount');
    }

    private function ensureRequiredComponents(): void
    {
        foreach (self::REQUIRED_COMPONENTS as $component) {
            PayrollComponent::query()->updateOrCreate(
                ['nama' => $component['nama']],
                $component + ['is_active' => true]
            );
        }
    }
}
