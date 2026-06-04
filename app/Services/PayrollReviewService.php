<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\PayrollComponent;
use App\Models\PayrollItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollReviewService
{
    public function __construct(
        private readonly PayrollAttendanceReadinessService $readinessService,
        private readonly PayrollValidationService $validationService
    ) {
    }

    public function updateManualAdjustments(Payroll $payroll, array $adjustments, ?int $userId = null): Payroll
    {
        $this->ensureUnlocked($payroll);
        $this->ensureStatus($payroll, ['draft', 'reviewed']);
        $components = PayrollComponent::query()
            ->where('is_active', true)
            ->where('input_mode', 'manual')
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($payroll, $adjustments, $components, $userId): void {
            $payroll->items()
                ->whereIn('component_id', $components->keys())
                ->delete();

            foreach ($adjustments as $adjustment) {
                $component = $components->get((int) $adjustment['component_id']);
                if (! $component) {
                    throw ValidationException::withMessages([
                        'adjustments' => 'Adjustment hanya boleh memakai komponen manual aktif.',
                    ]);
                }

                $amount = (int) $adjustment['amount'];
                if ($amount <= 0) {
                    continue;
                }

                PayrollItem::create([
                    'payroll_id' => $payroll->id,
                    'component_id' => $component->id,
                    'nama_item' => $component->nama,
                    'type' => $component->type,
                    'amount' => $amount,
                ]);
            }

            $this->refreshTotals($payroll);
            $payroll->forceFill(['approval_status' => 'reviewed'])->save();
            $this->validationService->validateAndStore($payroll->load(['karyawan', 'items.component']), $userId);
        });

        return $payroll->fresh(['karyawan', 'items.component']);
    }

    public function submit(Payroll $payroll, ?int $userId = null): Payroll
    {
        $this->ensureUnlocked($payroll);
        $this->ensureStatus($payroll, ['draft', 'reviewed']);
        $audit = $this->readinessService->audit([
            'start_date' => $payroll->periode_start->toDateString(),
            'end_date' => $payroll->periode_end->toDateString(),
            'employee_niks' => [$payroll->karyawan_nik],
        ]);

        $incompleteScanIssues = collect($audit['records']->first()['issues'] ?? [])
            ->filter(fn (array $issue) => ($issue['code'] ?? null) === 'incomplete_scan')
            ->values();

        if ($incompleteScanIssues->isNotEmpty()) {
            $issues = $incompleteScanIssues
                ->map(fn (array $issue) => "{$issue['date']}: {$issue['message']}")
                ->all();

            throw ValidationException::withMessages([
                'attendance' => 'Payroll belum dapat disubmit karena masih ada scan masuk/pulang belum lengkap.',
                'attendance_issues' => $issues,
            ]);
        }

        $validation = $this->validationService->validateAndStore(
            $payroll->load(['karyawan', 'items.component']),
            $userId
        );
        if ($validation['critical'] !== []) {
            throw ValidationException::withMessages(['payroll' => $validation['critical']]);
        }

        $payroll->forceFill([
            'approval_status' => 'submitted',
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ])->save();

        return $payroll->fresh(['karyawan', 'items.component']);
    }

    public function approve(Payroll $payroll, ?int $userId = null): Payroll
    {
        $this->ensureUnlocked($payroll);
        $this->ensureStatus($payroll, ['submitted']);
        $payroll->forceFill([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
        ])->save();

        return $payroll->fresh(['karyawan', 'items.component']);
    }

    public function cancelSubmit(Payroll $payroll): Payroll
    {
        $this->ensureUnlocked($payroll);
        $this->ensureStatus($payroll, ['submitted']);

        $payroll->forceFill([
            'approval_status' => 'reviewed',
            'submitted_by' => null,
            'submitted_at' => null,
        ])->save();

        return $payroll->fresh(['karyawan', 'items.component']);
    }

    public function cancelApprove(Payroll $payroll): Payroll
    {
        $this->ensureUnlocked($payroll);
        $this->ensureStatus($payroll, ['approved']);

        $payroll->forceFill([
            'approval_status' => 'submitted',
            'approved_by' => null,
            'approved_at' => null,
        ])->save();

        return $payroll->fresh(['karyawan', 'items.component']);
    }

    public function lock(Payroll $payroll, ?int $userId = null): Payroll
    {
        if ($payroll->is_locked) {
            return $payroll->fresh(['karyawan', 'items.component']);
        }

        $payroll->forceFill([
            'is_locked' => true,
            'locked_by' => $userId,
            'locked_at' => now(),
        ])->save();

        return $payroll->fresh(['karyawan', 'items.component']);
    }

    public function refreshTotals(Payroll $payroll): void
    {
        $items = $payroll->items()->get();
        $earnings = (int) $items->where('type', 'earning')->sum('amount');
        $deductions = (int) $items->where('type', 'deduction')->sum('amount');
        $netNeutralDeductions = (int) $items
            ->where('type', 'deduction')
            ->whereIn('nama_item', ['Pot. JKN Karyawan', 'Pot. JHT Karyawan', 'Pot. JP Karyawan'])
            ->sum('amount');

        $payroll->forceFill([
            'total_pendapatan' => $earnings,
            'total_potongan' => $deductions,
            'total_dibayarkan' => $earnings - ($deductions - $netNeutralDeductions),
        ])->save();
    }

    private function ensureStatus(Payroll $payroll, array $statuses): void
    {
        if (! in_array($payroll->approval_status, $statuses, true)) {
            throw ValidationException::withMessages([
                'payroll' => 'Status payroll tidak mengizinkan aksi ini.',
            ]);
        }
    }

    private function ensureUnlocked(Payroll $payroll): void
    {
        if ($payroll->is_locked) {
            throw ValidationException::withMessages([
                'payroll' => 'Payroll sudah dikunci dan tidak dapat diubah.',
            ]);
        }
    }
}
