<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Services\HrAttendanceReportService;
use App\Services\HrdAuditLogService;
use App\Services\PayrollCalculationService;
use App\Services\PayrollPeriodService;
use App\Services\PayrollReviewService;
use App\Services\PayrollSlipService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrPayrollProcessController extends Controller
{
    public function __construct(
        private readonly PayrollCalculationService $calculationService,
        private readonly PayrollReviewService $reviewService,
        private readonly PayrollPeriodService $periodService,
        private readonly PayrollSlipService $slipService
    ) {
    }

    public function preview(Request $request): JsonResponse
    {
        return response()->json($this->calculationService->preview($this->filters($request)));
    }

    public function generate(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $this->periodService->assertCompletedPayrollPeriod($filters);
        $result = $this->calculationService->generate($filters);
        app(HrdAuditLogService::class)->record(
            $request,
            'Proses Payroll',
            'created',
            "Generate draft payroll {$result['generated']} karyawan",
            null,
            $result,
            Payroll::class,
            null
        );

        return response()->json([
            'message' => "{$result['generated']} draft payroll berhasil dibuat.",
            ...$result,
        ]);
    }

    public function autoCorrect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'nik' => ['nullable', 'string'],
        ]);

        $normalizedFilters = [
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ];

        if (!empty($validated['nik'])) {
            $normalizedFilters['employee_niks'] = [$validated['nik']];
        }

        $filters = $this->periodService->normalizeFilters($normalizedFilters);

        $report = app(HrAttendanceReportService::class)->report($filters);
        $employees = !empty($validated['nik']) 
            ? collect($report['records'])->filter(fn($e) => $e['nik'] === $validated['nik']) 
            : collect($report['records']);

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Karyawan tidak ditemukan.'], 404);
        }

        $correctedCount = 0;
        $failures = [];
        foreach ($employees as $employee) {
            foreach ($employee['days'] as $date => $day) {
                if ($day['status'] === 'M' && (blank($day['scan_in']) || blank($day['scan_out']))) {
                    if (!blank($day['scan_in']) && blank($day['scan_out'])) {
                        $in = Carbon::createFromFormat('H:i:s', $day['scan_in']);
                        $out = $in->copy()->addHours(8);
                        
                        try {
                            $existingCorrection = AttendanceCorrection::query()
                                ->where('nik', $employee['nik'])
                                ->whereDate('attendance_date', $date)
                                ->first();
                            $beforeAudit = $existingCorrection ? app(HrdAuditLogService::class)->snapshot($existingCorrection) : null;

                            $correction = AttendanceCorrection::updateOrCreate(
                                ['nik' => $employee['nik'], 'attendance_date' => $date],
                                [
                                    'corrected_scan_in' => $in->format('H:i:s'),
                                    'corrected_scan_out' => $out->format('H:i:s'),
                                    'notes' => 'Diperbaiki otomatis oleh HRD (Lupa scan pulang)',
                                    'corrected_by' => $request->user()?->id,
                                    'has_missing_attendance_form' => false,
                                ]
                            );

                            app(HrdAuditLogService::class)->record(
                                $request,
                                'Koreksi Absensi',
                                $existingCorrection ? 'updated' : 'created',
                                "{$employee['nik']} - {$date}",
                                $beforeAudit,
                                $correction->fresh(),
                                AttendanceCorrection::class,
                                $correction->id
                            );

                            $correctedCount++;
                        } catch (\Throwable $e) {
                            $failures[] = [
                                'nik' => $employee['nik'],
                                'date' => $date,
                                'error' => $e->getMessage(),
                            ];
                        }
                    } elseif (blank($day['scan_in']) && !blank($day['scan_out'])) {
                        $out = Carbon::createFromFormat('H:i:s', $day['scan_out']);
                        $in = $out->copy()->subHours(8);
                        
                        try {
                            $existingCorrection = AttendanceCorrection::query()
                                ->where('nik', $employee['nik'])
                                ->whereDate('attendance_date', $date)
                                ->first();
                            $beforeAudit = $existingCorrection ? app(HrdAuditLogService::class)->snapshot($existingCorrection) : null;

                            $correction = AttendanceCorrection::updateOrCreate(
                                ['nik' => $employee['nik'], 'attendance_date' => $date],
                                [
                                    'corrected_scan_in' => $in->format('H:i:s'),
                                    'corrected_scan_out' => $out->format('H:i:s'),
                                    'notes' => 'Diperbaiki otomatis oleh HRD (Lupa scan masuk)',
                                    'corrected_by' => $request->user()?->id,
                                    'has_missing_attendance_form' => false,
                                ]
                            );

                            app(HrdAuditLogService::class)->record(
                                $request,
                                'Koreksi Absensi',
                                $existingCorrection ? 'updated' : 'created',
                                "{$employee['nik']} - {$date}",
                                $beforeAudit,
                                $correction->fresh(),
                                AttendanceCorrection::class,
                                $correction->id
                            );

                            $correctedCount++;
                        } catch (\Throwable $e) {
                            $failures[] = [
                                'nik' => $employee['nik'],
                                'date' => $date,
                                'error' => $e->getMessage(),
                            ];
                        }
                    }
                }
            }
        }

        $response = ['message' => "{$correctedCount} hari absensi berhasil dikoreksi otomatis."];
        if (!empty($failures)) {
            $response['failures'] = $failures;
        }

        return response()->json($response);
    }

    public function drafts(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $records = Payroll::query()
            ->with(['karyawan', 'items.component'])
            ->whereDate('periode_start', $filters['start_date'])
            ->whereDate('periode_end', $filters['end_date'])
            ->orderBy('karyawan_nik')
            ->get()
            ->map(fn (Payroll $payroll) => $this->payrollData($payroll));

        return response()->json([
            'records' => $records,
            'summary' => [
                'total_payrolls' => $records->count(),
                'total_gross' => $records->sum('total_pendapatan'),
                'total_net' => $records->sum('total_dibayarkan'),
                'total_lembur' => $records->sum(function ($r) {
                    $lembur = collect($r['items'])->firstWhere('name', 'Lembur');
                    return $lembur ? $lembur['amount'] : 0;
                }),
                'total_bpjs_perusahaan' => $records->sum('employer_contribution'),
                'total_bpjs_karyawan' => $records->sum(function ($r) {
                    $jkn = collect($r['items'])->where('type', 'deduction')->firstWhere('name', 'Tunjangan BPJS Kesehatan Karyawan');
                    $jht = collect($r['items'])->where('type', 'deduction')->firstWhere('name', 'Tunjangan JHT Karyawan');
                    $jp = collect($r['items'])->where('type', 'deduction')->firstWhere('name', 'Tunjangan JP Karyawan');
                    return ($jkn ? $jkn['amount'] : 0) + ($jht ? $jht['amount'] : 0) + ($jp ? $jp['amount'] : 0);
                }),
                'total_hari_masuk' => $records->sum('total_hari_masuk'),
                'total_extra_off_days' => $records->sum('extra_off_days'),
                'statuses' => $records->countBy('status'),
            ],
            'manual_components' => PayrollComponent::query()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query
                        ->where('input_mode', 'manual')
                        ->orWhere('nama', 'Lembur');
                })
                ->orderBy('type')
                ->orderBy('nama')
                ->get(['id', 'nama', 'type']),
        ]);
    }

    public function periods(): JsonResponse
    {
        $completedPeriods = collect($this->periodService->completedPeriods(12));
        $generatedPeriods = Payroll::query()
            ->select('periode_start', 'periode_end')
            ->distinct()
            ->orderByDesc('periode_end')
            ->orderByDesc('periode_start')
            ->get()
            ->map(fn (Payroll $payroll) => [
                'start_date' => $payroll->periode_start->toDateString(),
                'end_date' => $payroll->periode_end->toDateString(),
                'label' => $payroll->periode_start->format('d M Y').' - '.$payroll->periode_end->format('d M Y'),
                'can_generate' => $payroll->periode_end->lt(now()->startOfDay()),
            ]);

        $periods = $completedPeriods
            ->concat($generatedPeriods)
            ->unique(fn (array $period) => $period['start_date'].'|'.$period['end_date'])
            ->sortByDesc('end_date')
            ->values();

        return response()->json([
            'default_period' => $periods->first(),
            'data' => $periods,
        ]);
    }

    public function show(Payroll $payroll): JsonResponse
    {
        return response()->json(['data' => $this->payrollData($payroll->load(['karyawan', 'items.component']))]);
    }

    public function updateAdjustments(Request $request, Payroll $payroll): JsonResponse
    {
        $payload = $request->validate([
            'adjustments' => ['required', 'array'],
            'adjustments.*.component_id' => ['required', 'integer'],
            'adjustments.*.amount' => ['required', 'integer', 'min:0'],
        ]);
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payroll);
        $payroll = $this->reviewService->updateManualAdjustments($payroll, $payload['adjustments'], $request->user()?->id);
        app(HrdAuditLogService::class)->record(
            $request,
            'Proses Payroll',
            'updated',
            "Adjustment payroll {$payroll->karyawan_nik}",
            $beforeAudit,
            $payroll->fresh(),
            Payroll::class,
            $payroll->id
        );

        return response()->json(['message' => 'Adjustment manual berhasil disimpan.', 'data' => $this->payrollData($payroll)]);
    }

    public function submit(Request $request, Payroll $payroll): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payroll);
        $payroll = $this->reviewService->submit($payroll, $request->user()?->id);
        $this->recordPayrollStatusAudit($request, $beforeAudit, $payroll, 'Submit payroll');

        return response()->json(['message' => 'Payroll berhasil disubmit.', 'data' => $this->payrollData($payroll)]);
    }

    public function approve(Request $request, Payroll $payroll): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payroll);
        $payroll = $this->reviewService->approve($payroll, $request->user()?->id);
        $this->recordPayrollStatusAudit($request, $beforeAudit, $payroll, 'Approve payroll');

        return response()->json(['message' => 'Payroll berhasil disetujui.', 'data' => $this->payrollData($payroll)]);
    }

    public function cancelSubmit(Request $request, Payroll $payroll): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payroll);
        $payroll = $this->reviewService->cancelSubmit($payroll);
        $this->recordPayrollStatusAudit($request, $beforeAudit, $payroll, 'Cancel submit payroll');

        return response()->json(['message' => 'Submit payroll berhasil dibatalkan.', 'data' => $this->payrollData($payroll)]);
    }

    public function cancelApprove(Request $request, Payroll $payroll): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payroll);
        $payroll = $this->reviewService->cancelApprove($payroll);
        $this->recordPayrollStatusAudit($request, $beforeAudit, $payroll, 'Cancel approval payroll');

        return response()->json(['message' => 'Approval payroll berhasil dibatalkan.', 'data' => $this->payrollData($payroll)]);
    }

    public function lock(Request $request, Payroll $payroll): JsonResponse
    {
        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payroll);
        $payroll = $this->reviewService->lock($payroll, $request->user()?->id);
        $this->recordPayrollStatusAudit($request, $beforeAudit, $payroll, 'Lock payroll');

        return response()->json(['message' => 'Payroll berhasil dikunci.', 'data' => $this->payrollData($payroll)]);
    }

    public function downloadSlip(Payroll $payroll)
    {
        $pdf = $this->slipService->pdf($payroll);

        return response()->streamDownload(
            fn () => print($pdf['content']),
            $pdf['file_name'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function sendSlip(Request $request, Payroll $payroll): JsonResponse
    {
        $log = $this->slipService->send($payroll, $request->user()?->id);
        app(HrdAuditLogService::class)->record(
            $request,
            'Proses Payroll',
            'updated',
            "Kirim slip payroll {$payroll->karyawan_nik}",
            ['email_log_id' => null],
            ['email_log_id' => $log->id],
            Payroll::class,
            $payroll->id
        );

        return response()->json([
            'message' => 'Slip gaji berhasil dikirim.',
            'log_id' => $log->id,
            'data' => $this->payrollData($payroll->fresh(['karyawan', 'items.component'])),
        ]);
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'employee_niks' => ['nullable', 'array'],
            'employee_niks.*' => ['string', 'max:50'],
        ]);

        return $this->periodService->normalizeFilters($validated);
    }

    private function recordPayrollStatusAudit(Request $request, array $beforeAudit, Payroll $payroll, string $label): void
    {
        app(HrdAuditLogService::class)->record(
            $request,
            'Proses Payroll',
            'updated',
            "{$label} {$payroll->karyawan_nik}",
            $beforeAudit,
            $payroll->fresh(),
            Payroll::class,
            $payroll->id
        );
    }

    private function payrollData(Payroll $payroll): array
    {
        $items = $payroll->formatted_items->map(fn ($item) => [
            'id' => $item->id,
            'component_id' => $item->component_id,
            'name' => $item->component?->nama ?? $item->nama_item,
            'type' => $item->type,
            'amount' => (int) $item->amount,
            'input_mode' => $item->component?->input_mode ?? 'manual',
        ])->values();
        $employer = (int) $items->where('type', 'employer_contribution')->sum('amount');

        return [
            'id' => $payroll->id,
            'nik' => $payroll->karyawan_nik,
            'name' => $payroll->karyawan?->nama_karyawan ?? '-',
            'status' => $payroll->approval_status,
            'validation_status' => $payroll->validation_status,
            'validation_warnings' => $payroll->validation_warnings,
            'is_locked' => (bool) $payroll->is_locked,
            'total_pendapatan' => (int) $payroll->total_pendapatan,
            'total_potongan' => (int) $payroll->total_potongan,
            'total_dibayarkan' => (int) $payroll->total_dibayarkan,
            'employer_contribution' => $employer,
            'company_cost' => (int) $payroll->total_dibayarkan + $employer,
            'basic_salary' => (int) $payroll->basic_salary,
            'bruto_man_power' => (int) $payroll->bruto_man_power,
            'periode_hari_kerja' => (int) $payroll->hari_kerja,
            'total_hari_masuk' => (int) $payroll->total_hari_masuk,
            'extra_off_days' => (int) $payroll->extra_off_days,
            'tunjangan_tidak_tetap_full' => (int) $payroll->tunjangan_tidak_tetap_full,
            'formula_version' => $payroll->formula_version,
            'items' => $items,
        ];
    }

    public function exportDrafts(Request $request)
    {
        $filters = $this->filters($request);
        $fileName = 'Report_Payroll_' . ($filters['start_date'] ?? 'all') . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PayrollExport([
                'periode_start' => $filters['start_date'] ?? null,
                'periode_end' => $filters['end_date'] ?? null,
            ]),
            $fileName
        );
    }
}
