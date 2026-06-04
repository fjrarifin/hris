<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PayrollCalculationService;
use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Services\HrdAuditLogService;
use App\Services\PayrollReviewService;
use App\Services\PayrollPeriodService;
use App\Services\PayrollSlipService;
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
                'total_net' => $records->sum('total_dibayarkan'),
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
        $items = $payroll->items->map(fn ($item) => [
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
}
