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
        $validated = $this->validatedContract($request);

        $id = DB::table('t_kontrak_karyawan')->insertGetId([
            'nik' => $employee->nik,
            'kontrak_ke' => ((int) DB::table('t_kontrak_karyawan')->where('nik', $employee->nik)->max('kontrak_ke')) + 1,
            ...$validated,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'message' => 'Kontrak baru berhasil ditambahkan.',
            'data' => $this->serializeContract(DB::table('t_kontrak_karyawan')->find($id), now()->startOfDay()),
        ], 201);
    }

    public function update(Request $request, int $contractId): JsonResponse
    {
        $contract = DB::table('t_kontrak_karyawan')->where('id', $contractId)->first();
        abort_unless($contract, 404);

        DB::table('t_kontrak_karyawan')->where('id', $contractId)->update([
            ...$this->validatedContract($request),
            'updated_at' => now(),
        ]);

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
                $current = $history->first(fn (object $contract) => $this->isActive($contract, $today))
                    ?? $history->first();
                $contract = $current ? $this->serializeContract($current, $today) : null;

                return [
                    ...$this->employeeRow($employee),
                    'employee_status' => $employee->status_karyawan ?: '-',
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

        return [
            'id' => $contract->id,
            'contract_number' => $contract->kontrak_ke,
            'start_date' => $contract->start_date,
            'end_date' => $contract->end_date,
            'duration_months' => $contract->durasi_bulan,
            'status' => $contract->status_kontrak,
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
            'status_kontrak' => ['required', Rule::in(['AKTIF', 'DIPERPANJANG', 'SELESAI', 'HABIS', 'NONAKTIF'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'durasi_bulan' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            ...$validated,
            'status_kontrak' => strtoupper($validated['status_kontrak']),
        ];
    }
}
