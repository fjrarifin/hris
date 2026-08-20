<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KaryawanHolding;
use App\Models\QrHoldingTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HoldingEmployeeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = KaryawanHolding::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('nik', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhere('perusahaan', 'like', "%{$search}%")
                    ->orWhere('jabatan', 'like', "%{$search}%")
                    ->orWhere('departemen', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 15)));
        $employees = $query->orderByDesc('id')->paginate($perPage);

        $today = now()->format('ymd');
        $kpi = [
            'total_count' => KaryawanHolding::count(),
            'active_count' => KaryawanHolding::where('is_active', true)->count(),
            'inactive_count' => KaryawanHolding::where('is_active', false)->count(),
            'today_qr_count' => QrHoldingTransaction::where('access_date_code', $today)->count(),
        ];

        return response()->json([
            'data' => $employees->items(),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
            'kpi' => $kpi,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nik' => ['required', 'string', 'max:50', 'unique:m_karyawan_holding,nik'],
            'nama' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'departemen' => ['nullable', 'string', 'max:100'],
            'perusahaan' => ['nullable', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'nik.required' => 'NIK Karyawan Holding wajib diisi.',
            'nik.unique' => 'NIK Karyawan Holding ini sudah terdaftar di sistem.',
            'nama.required' => 'Nama Karyawan Holding wajib diisi.',
        ]);

        $validated['nik'] = strtoupper(trim($validated['nik']));
        $validated['is_active'] = $validated['is_active'] ?? true;

        $employee = KaryawanHolding::create($validated);

        return response()->json([
            'message' => 'Data Karyawan Holding berhasil ditambahkan.',
            'data' => $employee,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $employee = KaryawanHolding::findOrFail($id);

        return response()->json([
            'data' => $employee,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = KaryawanHolding::findOrFail($id);

        $validated = $request->validate([
            'nik' => [
                'required',
                'string',
                'max:50',
                Rule::unique('m_karyawan_holding', 'nik')->ignore($employee->id),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'departemen' => ['nullable', 'string', 'max:100'],
            'perusahaan' => ['nullable', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'is_active' => ['required', 'boolean'],
        ], [
            'nik.required' => 'NIK Karyawan Holding wajib diisi.',
            'nik.unique' => 'NIK Karyawan Holding ini sudah digunakan oleh data lain.',
            'nama.required' => 'Nama Karyawan Holding wajib diisi.',
        ]);

        $validated['nik'] = strtoupper(trim($validated['nik']));

        $employee->update($validated);

        return response()->json([
            'message' => 'Data Karyawan Holding berhasil diperbarui.',
            'data' => $employee,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $employee = KaryawanHolding::findOrFail($id);

        // Disassociate related logs if any
        QrHoldingTransaction::where('m_karyawan_holding_id', $employee->id)->update([
            'm_karyawan_holding_id' => null,
        ]);

        $employee->delete();

        return response()->json([
            'message' => 'Data Karyawan Holding berhasil dihapus.',
        ]);
    }

    public function qrLogs(Request $request): JsonResponse
    {
        $query = QrHoldingTransaction::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('nik', 'like', "%{$search}%")
                    ->orWhere('nama', 'like', "%{$search}%")
                    ->orWhere('perusahaan', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($date = $request->input('date')) {
            $query->whereDate('generated_at', $date);
        }

        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $logs = $query->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
