<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrdAuditLog;
use App\Models\Karyawan;
use App\Services\HrdAuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class HrContractController extends Controller
{
    private const CLOSED_STATUSES = ['SELESAI', 'HABIS', 'EXPIRED', 'NONAKTIF'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'active', 'expiring_60_days', 'expiring_45_days', 'expiring_30_days', 'expiring_two_months', 'expiring_this_month', 'expiring_next_month', 'expired', 'no_contract'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $today = now()->startOfDay();
        $this->expireContracts($today);
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
                fn (Collection $items) => match ($status) {
                    'expiring_two_months', 'expiring_60_days' => $items->filter(fn (array $item) => $this->isExpiringBetweenDays($item['contract'], $today, 46, 60)),
                    'expiring_45_days' => $items->filter(fn (array $item) => $this->isExpiringBetweenDays($item['contract'], $today, 31, 45)),
                    'expiring_30_days' => $items->filter(fn (array $item) => $this->isExpiringBetweenDays($item['contract'], $today, 0, 30)),
                    'all' => $items,
                    default => $items->where('contract_state', $status),
                }
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
                'expiring_60_days' => $records->filter(fn (array $item) => $this->isExpiringBetweenDays($item['contract'], $today, 46, 60))->count(),
                'expiring_45_days' => $records->filter(fn (array $item) => $this->isExpiringBetweenDays($item['contract'], $today, 31, 45))->count(),
                'expiring_30_days' => $records->filter(fn (array $item) => $this->isExpiringBetweenDays($item['contract'], $today, 0, 30))->count(),
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
        $this->expireContracts(now()->startOfDay(), $nik);
        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();

        return response()->json([
            'employee' => $this->employeeRow($employee),
            'contracts' => $this->contractsFor($employee->nik)
                ->map(fn (object $contract) => $this->serializeContract($contract, now()->startOfDay())),
            'audit_logs' => $this->contractAuditLogs($employee->nik),
        ]);
    }

    public function store(Request $request, string $nik): JsonResponse
    {
        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();
        $this->ensureNoActiveContract($employee->nik);
        $validated = $this->validatedContract($request);

        // Validate file if present
        if ($request->hasFile('document')) {
            $request->validate([
                'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            ]);
        }

        $document = null;
        try {
            // Store document if present
            if ($request->hasFile('document')) {
                $document = $request->file('document')->store('contract-documents', 'local');
            }

            $id = DB::table('t_kontrak_karyawan')->insertGetId([
                'nik' => $employee->nik,
                'kontrak_ke' => ((int) DB::table('t_kontrak_karyawan')->where('nik', $employee->nik)->max('kontrak_ke')) + 1,
                ...$validated,
                'document' => $document,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Clean up uploaded file if database insert fails
            if ($document && Storage::disk('local')->exists($document)) {
                Storage::disk('local')->delete($document);
            }

            throw $exception;
        }

        $this->syncEmployeeStatus($employee->nik);
        $createdContract = DB::table('t_kontrak_karyawan')->find($id);
        app(HrdAuditLogService::class)->record(
            $request,
            'Kontrak Karyawan',
            'created',
            "{$employee->nik} - kontrak {$createdContract->kontrak_ke}",
            null,
            $createdContract,
            't_kontrak_karyawan',
            $id
        );

        return response()->json([
            'message' => 'Kontrak baru berhasil ditambahkan.',
            'data' => $this->serializeContract($createdContract, now()->startOfDay()),
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

        // Validate file if present
        if ($request->hasFile('document')) {
            $request->validate([
                'document' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            ]);
        }

        $beforeAudit = app(HrdAuditLogService::class)->snapshot($contract);
        
        try {
            // Handle document update
            $updateData = [...$validated];
            
            if ($request->hasFile('document')) {
                // Delete old document if exists
                if ($contract->document && Storage::disk('local')->exists($contract->document)) {
                    Storage::disk('local')->delete($contract->document);
                }
                // Store new document
                $updateData['document'] = $request->file('document')->store('contract-documents', 'local');
            } elseif ($this->hasEmptyDocumentPlaceholder($request)) {
                // Clear document if empty placeholder sent
                $updateData['document'] = null;
            }
            
            DB::table('t_kontrak_karyawan')->where('id', $contractId)->update([
                ...$updateData,
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            throw $exception;
        }

        $this->syncEmployeeStatus((string) $contract->nik);
        $updatedContract = DB::table('t_kontrak_karyawan')->find($contractId);
        app(HrdAuditLogService::class)->record(
            $request,
            'Kontrak Karyawan',
            'updated',
            "{$updatedContract->nik} - kontrak {$updatedContract->kontrak_ke}",
            $beforeAudit,
            $updatedContract,
            't_kontrak_karyawan',
            $contractId
        );

        return response()->json([
            'message' => 'Kontrak berhasil diperbarui.',
            'data' => $this->serializeContract($updatedContract, now()->startOfDay()),
        ]);
    }

    public function previewPdf(int $contractId): JsonResponse
    {
        $contract = DB::table('t_kontrak_karyawan')->where('id', $contractId)->first();
        abort_unless($contract && $contract->document, 404);

        $disk = Storage::disk('local')->exists($contract->document) ? 'local' : 'public';
        abort_unless(Storage::disk($disk)->exists($contract->document), 404);

        $filename = 'Kontrak-'.$contract->nik.'-'.$contract->kontrak_ke.'.pdf';

        return response()->json([
            'filename' => $filename,
            'mime_type' => 'application/pdf',
            'content_base64' => base64_encode(Storage::disk($disk)->get($contract->document)),
        ])->header('Cache-Control', 'private, no-store');
    }

    private function employeeContractRows(Carbon $today): Collection
    {
        $contracts = DB::table('t_kontrak_karyawan')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('nik');

        return Karyawan::query()
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
            ->sort(function (array $left, array $right) use ($today): int {
                return [
                    $this->contractSortPriority($left['contract'], $today),
                    $left['contract']['end_date'] ?? '9999-12-31',
                    $left['name'],
                ] <=> [
                    $this->contractSortPriority($right['contract'], $today),
                    $right['contract']['end_date'] ?? '9999-12-31',
                    $right['name'],
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

    private function contractAuditLogs(string $nik): array
    {
        return HrdAuditLog::query()
            ->where('module', 'Kontrak Karyawan')
            ->where('subject_label', 'like', "{$nik} - kontrak%")
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (HrdAuditLog $log): array => [
                'id' => $log->id,
                'module' => $log->module,
                'action' => $log->action,
                'subject_label' => $log->subject_label,
                'changed_by_name' => $log->actor_name ?: 'User tidak diketahui',
                'source_label' => 'HRD',
                'changes' => $log->changes ?? [],
                'created_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->all();
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
            'has_document' => filled($contract->document),
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
            && (! $contract->start_date || Carbon::parse($contract->start_date)->subMonthNoOverflow()->lte($today))
            && (! $contract->end_date || Carbon::parse($contract->end_date)->gte($today));
    }

    private function isExpiringBetweenDays(?array $contract, Carbon $today, int $startDay, int $endDay): bool
    {
        if (! $contract
            || ! $contract['end_date']
            || in_array(strtoupper((string) $contract['status']), self::CLOSED_STATUSES, true)) {
            return false;
        }

        $remainingDays = (int) $today->diffInDays(Carbon::parse($contract['end_date'])->startOfDay());

        return $remainingDays >= $startDay && $remainingDays <= $endDay;
    }

    private function contractSortPriority(?array $contract, Carbon $today): int
    {
        if (! $contract || ! $contract['end_date']) {
            return 2;
        }

        return Carbon::parse($contract['end_date'])->startOfDay()->gte($today) ? 0 : 1;
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

    private function validatedContract(Request $request, bool $documentRequired = false): array
    {
        $validated = $request->validate([
            'jenis_kontrak' => ['required', Rule::in(['PKWT', 'PKWTT'])],
            'status_kontrak' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            // Remove document from validation here - it's handled separately in store/update
        ]);

        return [
            ...collect($validated)->all(),
            'jenis_kontrak' => strtoupper($validated['jenis_kontrak']),
            'status_kontrak' => strtoupper($validated['status_kontrak']),
            'durasi_bulan' => $this->durationMonths($validated['start_date'], $validated['end_date']),
        ];
    }

    private function hasEmptyDocumentPlaceholder(Request $request): bool
    {
        return $request->has('document')
            && ! $request->hasFile('document')
            && in_array($request->input('document'), ['null', ''], true);
    }

    private function ensureNoActiveContract(string $nik, ?int $exceptId = null): void
    {
        $today = now()->startOfDay();
        $hasActiveContract = DB::table('t_kontrak_karyawan')
            ->where('nik', $nik)
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $today->copy()->addMonthNoOverflow())
            ->whereDate('end_date', '>=', $today)
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

    private function expireContracts(Carbon $today, ?string $nik = null): void
    {
        $expiredNiks = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('end_date', '<', $today)
            ->when($nik !== null, fn ($query) => $query->where('nik', $nik))
            ->pluck('nik')
            ->unique()
            ->values();

        if ($expiredNiks->isEmpty()) {
            return;
        }

        DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('end_date', '<', $today)
            ->when($nik !== null, fn ($query) => $query->where('nik', $nik))
            ->update([
                'status_kontrak' => 'NONAKTIF',
                'updated_at' => now(),
            ]);

        $expiredNiks->each(fn (string $expiredNik) => $this->syncEmployeeStatus($expiredNik));
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
