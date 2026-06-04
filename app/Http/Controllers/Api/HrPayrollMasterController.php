<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeePayrollProfile;
use App\Models\Karyawan;
use App\Models\PayrollComponent;
use App\Services\HrdAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HrPayrollMasterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'readiness' => ['nullable', Rule::in(['all', 'ready', 'incomplete'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim((string) ($validated['q'] ?? ''));
        $readiness = $validated['readiness'] ?? 'all';

        $employees = Karyawan::query()
            ->with('payrollProfile')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($filter) use ($search): void {
                    $filter->where('nik', 'like', "%{$search}%")
                        ->orWhere('nama_karyawan', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%")
                        ->orWhere('departement', 'like', "%{$search}%")
                        ->orWhere('divisi', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_karyawan')
            ->get()
            ->map(fn (Karyawan $employee): array => $this->employeeData($employee))
            ->when($readiness === 'ready', fn ($records) => $records->where('is_ready', true))
            ->when($readiness === 'incomplete', fn ($records) => $records->where('is_ready', false))
            ->values();

        $perPage = 15;
        $page = min((int) ($validated['page'] ?? 1), max((int) ceil($employees->count() / $perPage), 1));

        return response()->json([
            'summary' => [
                'total_employees' => $employees->count(),
                'ready' => $employees->where('is_ready', true)->count(),
                'incomplete' => $employees->where('is_ready', false)->count(),
                'bpjs_active' => $employees->where('bpjs', true)->count(),
            ],
            'records' => $employees->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $employees->count(),
                'last_page' => max((int) ceil($employees->count() / $perPage), 1),
                'from' => $employees->isEmpty() ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $employees->count()),
            ],
        ]);
    }

    public function show(string $nik): JsonResponse
    {
        return response()->json([
            'data' => $this->employeeData(Karyawan::query()->with('payrollProfile')->where('nik', $nik)->firstOrFail()),
        ]);
    }

    public function update(Request $request, string $nik): JsonResponse
    {
        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();
        $payload = $request->validate([
            'gaji_pokok' => ['required', 'integer', 'min:0'],
            'tunjangan_jabatan' => ['required', 'integer', 'min:0'],
            'tunjangan_tidak_tetap' => ['nullable', 'integer', 'min:0'],
            'bruto_man_power' => ['required', 'integer', 'min:0'],
            'payroll_group' => ['required', Rule::in(['staff', 'operator'])],
            'dasar_bpjs' => ['required', 'integer', 'min:0'],
            'dasar_jp' => ['required', 'integer', 'min:0'],
            'rate_jkk_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload['tunjangan_tidak_tetap'] = (int) ($payload['tunjangan_tidak_tetap'] ?? 0);
        $existingProfile = EmployeePayrollProfile::query()->where('karyawan_nik', $employee->nik)->first();
        $beforeAudit = $existingProfile ? app(HrdAuditLogService::class)->snapshot($existingProfile) : null;
        $profile = EmployeePayrollProfile::updateOrCreate(
            ['karyawan_nik' => $employee->nik],
            [...$payload, 'updated_by' => $request->user()?->id]
        );
        app(HrdAuditLogService::class)->record(
            $request,
            'Master Payroll',
            $existingProfile ? 'updated' : 'created',
            "{$employee->nik} - {$employee->nama_karyawan}",
            $beforeAudit,
            $profile->fresh(),
            EmployeePayrollProfile::class,
            $employee->nik
        );

        return response()->json([
            'message' => 'Master payroll karyawan berhasil disimpan.',
            'data' => $this->employeeData($employee->setRelation('payrollProfile', $profile)),
        ]);
    }

    public function components(): JsonResponse
    {
        return response()->json([
            'data' => PayrollComponent::query()
                ->orderBy('type')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function updateComponent(Request $request, PayrollComponent $payrollComponent): JsonResponse
    {
        $payload = $request->validate([
            'type' => ['required', Rule::in(['earning', 'deduction', 'employer_contribution'])],
            'input_mode' => ['required', Rule::in(['manual', 'calculated'])],
            'is_active' => ['required', 'boolean'],
        ]);

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($payrollComponent);
        $payrollComponent->update($payload);
        app(HrdAuditLogService::class)->record(
            $request,
            'Komponen Payroll',
            'updated',
            $payrollComponent->nama,
            $beforeAudit,
            $payrollComponent->fresh(),
            PayrollComponent::class,
            $payrollComponent->id
        );

        return response()->json([
            'message' => 'Pengaturan komponen payroll berhasil disimpan.',
            'data' => $payrollComponent->fresh(),
        ]);
    }

    private function employeeData(Karyawan $employee): array
    {
        $profile = $employee->payrollProfile;
        $requiredFields = [
            'gaji_pokok' => $profile?->gaji_pokok,
            'bruto_man_power' => $profile?->bruto_man_power,
        ];

        $missingFields = collect($requiredFields)
            ->filter(fn ($value) => (int) $value <= 0)
            ->keys()
            ->values();

        if ($profile && ! $profile->is_active) {
            $missingFields->push('master_nonaktif');
        }

        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'department' => $employee->departement ?: ($employee->divisi ?: '-'),
            'employee_status' => $employee->status_karyawan ?: '-',
            'bpjs' => (bool) $employee->bpjs,
            'no_bpjs' => $employee->no_bpjs,
            'profile' => [
                'gaji_pokok' => (int) ($profile?->gaji_pokok ?? 0),
                'tunjangan_jabatan' => (int) ($profile?->tunjangan_jabatan ?? 0),
                'tunjangan_tidak_tetap' => (int) ($profile?->tunjangan_tidak_tetap ?? 0),
                'bruto_man_power' => (int) ($profile?->bruto_man_power ?? 0),
                'payroll_group' => $profile?->payroll_group ?: $this->defaultPayrollGroup($employee),
                'dasar_bpjs' => (int) ($profile?->dasar_bpjs ?? 0),
                'dasar_jp' => (int) ($profile?->dasar_jp ?? 0),
                'rate_jkk_percent' => (string) ($profile?->rate_jkk_percent ?? '0.54'),
                'is_active' => (bool) ($profile?->is_active ?? true),
                'notes' => $profile?->notes,
            ],
            'is_ready' => $missingFields->isEmpty(),
            'missing_fields' => $missingFields,
            'updated_at' => $profile?->updated_at?->toIso8601String(),
        ];
    }

    private function defaultPayrollGroup(Karyawan $employee): string
    {
        $position = strtolower($employee->posisi ?: $employee->jabatan ?: $employee->posisi_title ?: '');

        return str_contains($position, 'operator') ? 'operator' : 'staff';
    }
}
