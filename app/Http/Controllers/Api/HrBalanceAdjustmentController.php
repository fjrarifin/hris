<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeExtraOff;
use App\Models\EmployeePhAdjustment;
use App\Models\Karyawan;
use App\Models\LeaveAccrual;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrBalanceAdjustmentController extends Controller
{
    /**
     * Searchable list of employees for selection dropdowns.
     */
    public function employees(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));
        $employees = Karyawan::query()
            ->with('user:id,username,name')
            ->where(function ($q): void {
                $q->whereNull('status_karyawan')
                    ->orWhereRaw("UPPER(TRIM(COALESCE(status_karyawan, ''))) = ?", ['AKTIF']);
            })
            ->when($search, function ($q) use ($search): void {
                $q->where(function ($sub) use ($search): void {
                    $sub->where('nama_karyawan', 'like', "%{$search}%")
                        ->orWhere('nik', 'like', "%{$search}%")
                        ->orWhere('jabatan', 'like', "%{$search}%")
                        ->orWhere('posisi', 'like', "%{$search}%")
                        ->orWhere('departement', 'like', "%{$search}%")
                        ->orWhere('divisi', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama_karyawan')
            ->get(['nik', 'pin', 'nama_karyawan', 'jabatan', 'posisi', 'divisi', 'departement', 'join_date']);

        return response()->json([
            'data' => $employees,
        ]);
    }

    // ==========================================
    // 1. LEAVE (CUTI TAHUNAN) ADJUSTMENT
    // ==========================================

    public function leaveIndex(Request $request): JsonResponse
    {
        $query = LeaveAccrual::query()->with(['user.karyawan']);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('nik', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('user.karyawan', function ($sub) use ($search): void {
                        $sub->where('nama_karyawan', 'like', "%{$search}%")
                            ->orWhere('jabatan', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $items = $query->orderByDesc('id')->paginate($perPage);

        $kpi = [
            'total_adjustments' => LeaveAccrual::count(),
            'total_positive_days' => (int) LeaveAccrual::where('days', '>', 0)->sum('days'),
            'total_deducted_days' => abs((int) LeaveAccrual::where('days', '<', 0)->sum('days')),
        ];

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'kpi' => $kpi,
        ]);
    }

    public function leaveStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'karyawan_nik' => ['required', 'string', 'exists:m_karyawan,nik'],
            'type' => ['required', 'string', 'in:add,deduct'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'expired_at' => ['nullable', 'date'],
            'notes' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'karyawan_nik.required' => 'Karyawan wajib dipilih.',
            'karyawan_nik.exists' => 'Data karyawan tidak ditemukan.',
            'type.required' => 'Pilih aksi penambahan atau pengurangan saldo.',
            'days.required' => 'Jumlah hari penyesuaian wajib diisi.',
            'days.min' => 'Jumlah hari minimal 1.',
            'notes.required' => 'Alasan / keterangan penyesuaian wajib diisi.',
        ]);

        $employee = Karyawan::with('user')->where('nik', $validated['karyawan_nik'])->firstOrFail();
        $user = $employee->user;

        if (! $user) {
            // Find or create User account if not exists
            $user = User::firstOrCreate(
                ['username' => $employee->nik],
                [
                    'name' => $employee->nama_karyawan,
                    'email' => $employee->email ?: strtolower($employee->nik).'@hompimplay.id',
                    'password' => bcrypt('123456'),
                    'level' => 3,
                    'must_change_password' => false,
                ]
            );
        }

        $effectiveDays = $validated['type'] === 'deduct' ? -abs((int) $validated['days']) : abs((int) $validated['days']);
        $expiredAt = ! empty($validated['expired_at'])
            ? Carbon::parse($validated['expired_at'])->toDateString()
            : now()->addYear()->endOfMonth()->toDateString();

        $prefix = $validated['type'] === 'deduct' ? '[ADJUSTMENT PENGURANGAN]' : '[ADJUSTMENT PENAMBAHAN]';
        $fullNotes = trim($prefix.' '.$validated['notes']);

        $accrual = LeaveAccrual::create([
            'user_id' => $user->id,
            'nik' => $employee->nik,
            'year' => now()->year,
            'month' => now()->month,
            'accrued_at' => now()->toDateString(),
            'days' => $effectiveDays,
            'expired_at' => $expiredAt,
            'is_used' => false,
            'notes' => $fullNotes,
        ]);

        return response()->json([
            'message' => 'Adjustment saldo cuti berhasil disimpan.',
            'data' => $accrual,
        ], 201);
    }

    public function leaveDestroy(int $id): JsonResponse
    {
        $item = LeaveAccrual::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Data adjustment cuti berhasil dihapus.',
        ]);
    }

    public function employeeHolidays(Request $request, string $nik): JsonResponse
    {
        $employee = Karyawan::with('user')->where('nik', $nik)->firstOrFail();
        $user = $employee->user;
        $type = $request->query('type', 'deduct');

        $attendedDates = $employee->pin
            ? \App\Models\FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [now()->subDays(90)->startOfDay(), now()->startOfDay()])
                ->get(['scan_date'])
                ->pluck('scan_date')
                ->map(fn (Carbon $date) => $date->toDateString())
                ->unique()
            : collect();

        $usedHolidayIds = $user
            ? \App\Models\PublicHolidayRequest::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->pluck('public_holiday_id')
            : collect();

        $deductedHolidayIds = EmployeePhAdjustment::query()
            ->where('karyawan_nik', $employee->nik)
            ->whereNotNull('public_holiday_id')
            ->where('days', '<', 0)
            ->pluck('public_holiday_id');

        $addedHolidayIds = EmployeePhAdjustment::query()
            ->where('karyawan_nik', $employee->nik)
            ->whereNotNull('public_holiday_id')
            ->where('days', '>', 0)
            ->pluck('public_holiday_id');

        $joinDate = $employee->join_date ? Carbon::parse($employee->join_date)->startOfDay() : null;

        if ($type === 'add') {
            // Untuk penambahan, sediakan seluruh hari libur nasional aktif dalam 90 hari terakhir
            $holidays = \App\Models\PublicHoliday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', '<=', now()->addDays(30))
                ->whereDate('holiday_date', '>', now()->subDays(90))
                ->when($joinDate, fn ($q) => $q->whereDate('holiday_date', '>=', $joinDate))
                ->orderByDesc('holiday_date')
                ->get()
                ->values()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'holiday_date' => $h->holiday_date?->toDateString(),
                    'label' => ($h->holiday_date ? $h->holiday_date->format('d M Y') : '') . ' - ' . $h->name,
                ]);
        } else {
            // Untuk pengurangan, hanya libur yang saat ini aktif dimiliki karyawan
            $excludeIds = $usedHolidayIds->merge($deductedHolidayIds)->unique();

            $holidays = \App\Models\PublicHoliday::query()
                ->where('is_active', true)
                ->whereDate('holiday_date', '<', now())
                ->whereDate('holiday_date', '>', now()->subDays(90))
                ->when($joinDate, fn ($q) => $q->whereDate('holiday_date', '>=', $joinDate))
                ->whereNotIn('id', $excludeIds)
                ->orderByDesc('holiday_date')
                ->get()
                ->filter(function ($holiday) use ($attendedDates, $addedHolidayIds) {
                    if ($addedHolidayIds->contains($holiday->id)) return true;
                    $requiresAttendance = $holiday->holiday_date->gte(Carbon::parse('2026-05-27'));
                    return ! $requiresAttendance || $attendedDates->contains($holiday->holiday_date->toDateString());
                })
                ->values()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'name' => $h->name,
                    'holiday_date' => $h->holiday_date?->toDateString(),
                    'label' => ($h->holiday_date ? $h->holiday_date->format('d M Y') : '') . ' - ' . $h->name,
                ]);
        }

        return response()->json([
            'data' => $holidays,
        ]);
    }

    // ==========================================
    // 2. PUBLIC HOLIDAY (PH) ADJUSTMENT
    // ==========================================

    public function phIndex(Request $request): JsonResponse
    {
        $query = EmployeePhAdjustment::query()->with([
            'karyawan',
            'creator:id,name,username',
            'holiday:id,name,holiday_date',
        ]);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('karyawan_nik', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('karyawan', function ($sub) use ($search): void {
                        $sub->where('nama_karyawan', 'like', "%{$search}%")
                            ->orWhere('jabatan', 'like', "%{$search}%");
                    })
                    ->orWhereHas('holiday', function ($sub) use ($search): void {
                        $sub->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $items = $query->orderByDesc('id')->paginate($perPage);

        $kpi = [
            'total_adjustments' => EmployeePhAdjustment::count(),
            'total_positive_days' => (int) EmployeePhAdjustment::where('days', '>', 0)->sum('days'),
            'total_deducted_days' => abs((int) EmployeePhAdjustment::where('days', '<', 0)->sum('days')),
        ];

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'kpi' => $kpi,
        ]);
    }

    public function phStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'karyawan_nik' => ['required', 'string', 'exists:m_karyawan,nik'],
            'type' => ['required', 'string', 'in:add,deduct'],
            'public_holiday_id' => ['nullable', 'integer', 'exists:public_holidays,id'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'adjustment_date' => ['nullable', 'date'],
            'notes' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'karyawan_nik.required' => 'Karyawan wajib dipilih.',
            'karyawan_nik.exists' => 'Data karyawan tidak ditemukan.',
            'type.required' => 'Pilih aksi penambahan atau pengurangan saldo.',
            'days.required' => 'Jumlah hari penyesuaian wajib diisi.',
            'days.min' => 'Jumlah hari minimal 1.',
            'notes.required' => 'Alasan / keterangan penyesuaian wajib diisi.',
        ]);

        $employee = Karyawan::with('user')->where('nik', $validated['karyawan_nik'])->firstOrFail();
        $effectiveDays = $validated['type'] === 'deduct' ? -abs((int) $validated['days']) : abs((int) $validated['days']);
        $adjustmentDate = ! empty($validated['adjustment_date'])
            ? Carbon::parse($validated['adjustment_date'])->toDateString()
            : now()->toDateString();

        $publicHolidayId = ! empty($validated['public_holiday_id'])
            ? (int) $validated['public_holiday_id']
            : null;

        $adjustment = EmployeePhAdjustment::create([
            'user_id' => $employee->user?->id,
            'karyawan_nik' => $employee->nik,
            'public_holiday_id' => $publicHolidayId,
            'days' => $effectiveDays,
            'adjustment_date' => $adjustmentDate,
            'notes' => trim((string) $validated['notes']),
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'Adjustment saldo Public Holiday berhasil disimpan.',
            'data' => $adjustment->load('holiday'),
        ], 201);
    }

    public function phDestroy(int $id): JsonResponse
    {
        $item = EmployeePhAdjustment::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Data adjustment Public Holiday berhasil dihapus.',
        ]);
    }

    // ==========================================
    // 3. EXTRA OFF (EO) ADJUSTMENT
    // ==========================================

    public function extraOffIndex(Request $request): JsonResponse
    {
        $query = EmployeeExtraOff::query()->with(['karyawan']);

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('karyawan_nik', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('karyawan', function ($sub) use ($search): void {
                        $sub->where('nama_karyawan', 'like', "%{$search}%")
                            ->orWhere('jabatan', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $items = $query->orderByDesc('id')->paginate($perPage);

        $kpi = [
            'total_records' => EmployeeExtraOff::count(),
            'total_granted_days' => (int) EmployeeExtraOff::where('days', '>', 0)->sum('days'),
            'total_deducted_days' => abs((int) EmployeeExtraOff::where('days', '<', 0)->sum('days')),
        ];

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'kpi' => $kpi,
        ]);
    }

    public function extraOffStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'karyawan_nik' => ['required', 'string', 'exists:m_karyawan,nik'],
            'type' => ['required', 'string', 'in:add,deduct'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'periode_start' => ['nullable', 'date'],
            'periode_end' => ['nullable', 'date'],
            'notes' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'karyawan_nik.required' => 'Karyawan wajib dipilih.',
            'karyawan_nik.exists' => 'Data karyawan tidak ditemukan.',
            'type.required' => 'Pilih aksi penambahan atau pengurangan saldo.',
            'days.required' => 'Jumlah hari penyesuaian wajib diisi.',
            'days.min' => 'Jumlah hari minimal 1.',
            'notes.required' => 'Alasan / keterangan penyesuaian wajib diisi.',
        ]);

        $employee = Karyawan::where('nik', $validated['karyawan_nik'])->firstOrFail();
        $effectiveDays = $validated['type'] === 'deduct' ? -abs((int) $validated['days']) : abs((int) $validated['days']);

        $periodeStart = ! empty($validated['periode_start'])
            ? Carbon::parse($validated['periode_start'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $periodeEnd = ! empty($validated['periode_end'])
            ? Carbon::parse($validated['periode_end'])->toDateString()
            : now()->endOfMonth()->toDateString();

        $prefix = $validated['type'] === 'deduct' ? '[ADJUSTMENT PENGURANGAN]' : '[ADJUSTMENT PENAMBAHAN]';
        $fullNotes = trim($prefix.' '.$validated['notes']);

        $record = EmployeeExtraOff::create([
            'karyawan_nik' => $employee->nik,
            'periode_start' => $periodeStart,
            'periode_end' => $periodeEnd,
            'days' => $effectiveDays,
            'source' => 'ADJUSTMENT',
            'notes' => $fullNotes,
        ]);

        return response()->json([
            'message' => 'Adjustment saldo Extra Off berhasil disimpan.',
            'data' => $record,
        ], 201);
    }

    public function extraOffDestroy(int $id): JsonResponse
    {
        $item = EmployeeExtraOff::findOrFail($id);
        $item->delete();

        return response()->json([
            'message' => 'Data adjustment Extra Off berhasil dihapus.',
        ]);
    }
}
