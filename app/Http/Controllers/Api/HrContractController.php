<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrContractController extends Controller
{
    private const CLOSED_STATUSES = ['SELESAI', 'HABIS', 'EXPIRED', 'NONAKTIF'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'expiring_this_month', 'expiring_next_month', 'expired', 'no_contract'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = now()->startOfDay();
        $records = $this->employeeContractRows($today);
        $search = strtolower(trim((string) ($validated['q'] ?? '')));
        $status = $validated['status'] ?? 'all';
        $filtered = $records
            ->when($search !== '', fn (Collection $items) => $items->filter(
                fn (array $item) => str_contains(strtolower(implode(' ', [
                    $item['nik'],
                    $item['name'],
                    $item['position'],
                    $item['department'],
                ])), $search)
            ))
            ->when(
                $status === 'active',
                fn (Collection $items) => $items->filter(fn (array $item) => in_array(
                    $item['contract_state'],
                    ['active', 'expiring_this_month', 'expiring_next_month'],
                    true
                )),
                fn (Collection $items) => $status !== 'all' ? $items->where('contract_state', $status) : $items
            )
            ->values();

        $perPage = 10;
        $page = min((int) ($validated['page'] ?? 1), max((int) ceil($filtered->count() / $perPage), 1));
        $pagination = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page
        );

        return response()->json([
            'as_of_date' => $today->toDateString(),
            'summary' => [
                'active' => $records->filter(fn (array $item) => in_array(
                    $item['contract_state'],
                    ['active', 'expiring_this_month', 'expiring_next_month'],
                    true
                ))->count(),
                'expiring_this_month' => $records->where('contract_state', 'expiring_this_month')->count(),
                'expiring_next_month' => $records->where('contract_state', 'expiring_next_month')->count(),
                'expired' => $records->where('contract_state', 'expired')->count(),
                'no_contract' => $records->where('contract_state', 'no_contract')->count(),
            ],
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $status,
            ],
            'records' => $pagination->items(),
            'pagination' => [
                'current_page' => $pagination->currentPage(),
                'per_page' => $pagination->perPage(),
                'total' => $pagination->total(),
                'last_page' => $pagination->lastPage(),
                'from' => $pagination->firstItem() ?? 0,
                'to' => $pagination->lastItem() ?? 0,
            ],
        ]);
    }

    public function show(string $nik): JsonResponse
    {
        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();

        return response()->json([
            'employee' => $this->employeeRow($employee),
            'contracts' => $this->contractsFor($employee->nik)
                ->map(fn (object $contract) => $this->serializeContract($contract, now()->startOfDay())),
        ]);
    }

    public function store(Request $request, string $nik): JsonResponse
    {
        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();
        $this->ensureNoActiveContract($employee->nik);
        $validated = $this->validatedContract($request);

        $id = DB::table('t_kontrak_karyawan')->insertGetId([
            'nik' => $employee->nik,
            'kontrak_ke' => ((int) DB::table('t_kontrak_karyawan')->where('nik', $employee->nik)->max('kontrak_ke')) + 1,
            ...$validated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->syncEmployeeStatus($employee->nik);

        return response()->json([
            'message' => 'Kontrak baru berhasil ditambahkan.',
            'data' => $this->serializeContract(DB::table('t_kontrak_karyawan')->find($id), now()->startOfDay()),
        ], 201);
    }

    public function update(Request $request, int $contractId): JsonResponse
    {
        $contract = DB::table('t_kontrak_karyawan')->where('id', $contractId)->first();
        abort_unless($contract, 404);

        $validated = $this->validatedContract($request);
        if ($validated['status_kontrak'] === 'AKTIF') {
            $this->ensureNoActiveContract((string) $contract->nik, $contractId);
        }

        DB::table('t_kontrak_karyawan')->where('id', $contractId)->update([
            ...$validated,
            'updated_at' => now(),
        ]);
        $this->syncEmployeeStatus((string) $contract->nik);

        return response()->json([
            'message' => 'Kontrak berhasil diperbarui.',
            'data' => $this->serializeContract(DB::table('t_kontrak_karyawan')->find($contractId), now()->startOfDay()),
        ]);
    }

    private function employeeContractRows(Carbon $today): Collection
    {
        $contracts = DB::table('t_kontrak_karyawan')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('nik');

        return Karyawan::query()
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi', 'unit', 'status_karyawan'])
            ->map(function (Karyawan $employee) use ($contracts, $today): array {
                $history = $contracts->get($employee->nik, collect());
                $active = $history->first(fn (object $contract) => $this->isActive($contract, $today));
                $current = $active ?? $history->first();
                $contract = $current ? $this->serializeContract($current, $today) : null;

                return [
                    ...$this->employeeRow($employee),
                    'employee_status' => $active ? 'AKTIF' : 'NONAKTIF',
                    'contracts_count' => $history->count(),
                    'contract' => $contract,
                    'contract_state' => $contract['state'] ?? 'no_contract',
                ];
            })
            ->values();
    }

    private function contractsFor(string $nik): Collection
    {
        return DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }

    private function serializeContract(object $contract, Carbon $today): array
    {
        $endDate = $contract->end_date ? Carbon::parse($contract->end_date)->startOfDay() : null;
        $durationMonths = $this->durationMonths($contract->start_date, $contract->end_date);

        return [
            'id' => $contract->id,
            'contract_number' => $contract->kontrak_ke,
            'contract_type' => $contract->jenis_kontrak ?? 'PKWT',
            'start_date' => $contract->start_date,
            'end_date' => $contract->end_date,
            'duration_months' => $durationMonths,
            'duration_label' => $this->durationLabel($durationMonths),
            'status' => $contract->status_kontrak,
            'description' => $contract->keterangan ?? null,
            'state' => $this->contractState($contract, $today),
            'remaining_days' => $endDate && $endDate->gte($today)
                ? (int) $today->diffInDays($endDate)
                : null,
        ];
    }

    private function contractState(object $contract, Carbon $today): string
    {
        $status = strtoupper((string) $contract->status_kontrak);
        $endDate = $contract->end_date ? Carbon::parse($contract->end_date)->startOfDay() : null;

        if (in_array($status, self::CLOSED_STATUSES, true) || ($endDate && $endDate->lt($today))) {
            return 'expired';
        }

        if ($endDate && $endDate->between($today, $today->copy()->endOfMonth())) {
            return 'expiring_this_month';
        }

        if ($endDate && $endDate->between($today->copy()->addMonthNoOverflow()->startOfMonth(), $today->copy()->addMonthNoOverflow()->endOfMonth())) {
            return 'expiring_next_month';
        }

        return 'active';
    }

    private function isActive(object $contract, Carbon $today): bool
    {
        return ! in_array(strtoupper((string) $contract->status_kontrak), self::CLOSED_STATUSES, true)
            && (! $contract->start_date || Carbon::parse($contract->start_date)->lte($today))
            && (! $contract->end_date || Carbon::parse($contract->end_date)->gte($today));
    }

    private function employeeRow(Karyawan $employee): array
    {
        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'department' => $employee->departement ?: ($employee->divisi ?: '-'),
            'unit' => $employee->unit ?: '-',
        ];
    }

    private function validatedContract(Request $request): array
    {
        $validated = $request->validate([
            'jenis_kontrak' => ['required', Rule::in(['PKWT', 'PKWTT'])],
            'status_kontrak' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        return [
            ...$validated,
            'jenis_kontrak' => strtoupper($validated['jenis_kontrak']),
            'status_kontrak' => strtoupper($validated['status_kontrak']),
            'durasi_bulan' => $this->durationMonths($validated['start_date'], $validated['end_date']),
        ];
    }

    private function ensureNoActiveContract(string $nik, ?int $exceptId = null): void
    {
        $hasActiveContract = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->where('status_kontrak', 'AKTIF')
            ->when($exceptId !== null, fn ($query) => $query->where('id', '!=', $exceptId))
            ->exists();

        if (! $hasActiveContract) {
            return;
        }

        throw ValidationException::withMessages([
            'status_kontrak' => [
                'Kontrak yang masih AKTIF harus diubah menjadi NONAKTIF sebelum menambahkan atau mengaktifkan kontrak baru.',
            ],
        ]);
    }

    private function syncEmployeeStatus(string $nik): void
    {
        $today = now()->startOfDay();
        $active = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->get()
            ->contains(fn (object $contract) => $this->isActive($contract, $today));

        Karyawan::query()->where('nik', $nik)->update([
            'status_karyawan' => $active ? 'AKTIF' : 'NONAKTIF',
        ]);
    }

    private function durationMonths(?string $startDate, ?string $endDate): ?int
    {
        if (! $startDate || ! $endDate) {
            return null;
        }

        return (int) round(Carbon::parse($startDate)->diffInMonths(Carbon::parse($endDate)->addDay()));
    }

    private function durationLabel(?int $months): string
    {
        if (! $months) {
            return '-';
        }

        $years = intdiv($months, 12);
        $remainingMonths = $months % 12;
        $parts = [];

        if ($years) {
            $parts[] = $years.' tahun';
        }

        if ($remainingMonths) {
            $parts[] = $remainingMonths.' bulan';
        }

        return implode(' ', $parts);
    }
}
