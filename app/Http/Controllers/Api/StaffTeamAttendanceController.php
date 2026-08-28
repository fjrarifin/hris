<?php

namespace App\Http\Controllers\Api;

use App\Exports\HrAttendanceCorrectionExport;
use App\Http\Controllers\Controller;
use App\Models\Karyawan;
use App\Services\HrAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StaffTeamAttendanceController extends Controller
{
    public function __construct(private readonly HrAttendanceReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $supervisor = $this->supervisorFor($request);
        $employees = $this->manageableEmployees($supervisor);

        if ($employees->isEmpty()) {
            return response()->json([
                'supervisor' => [
                    'nik' => $supervisor->nik,
                    'name' => $supervisor->nama_karyawan,
                    'position' => $supervisor->jabatan ?: ($supervisor->posisi ?: '-'),
                ],
                'employees' => [],
                'summary' => [
                    'total_employees' => 0,
                    'total_days_tracked' => 0,
                    'present_count' => 0,
                    'late_count' => 0,
                    'leave_permission_count' => 0,
                    'alpha_count' => 0,
                ],
                'records' => [],
                'pagination' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'total' => 0,
                    'last_page' => 1,
                ],
            ]);
        }

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'employee_nik' => ['nullable', 'string'],
            'status_filter' => ['nullable', 'string', 'in:all,present,late,leave_permission,alpha,attention'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $start = Carbon::parse($validated['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($validated['end_date'] ?? now()->toDateString())->startOfDay();

        if ($start->diffInDays($end) > 60) {
            throw ValidationException::withMessages([
                'end_date' => ['Rentang tanggal maksimal 60 hari.'],
            ]);
        }

        $selectedEmployeeNik = $validated['employee_nik'] ?? null;
        $targetNiks = $selectedEmployeeNik
            ? $employees->where('nik', $selectedEmployeeNik)->pluck('nik')->all()
            : $employees->pluck('nik')->all();

        if (empty($targetNiks)) {
            $targetNiks = $employees->pluck('nik')->all();
        }

        $report = $this->reportService->report([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'employee_niks' => $targetNiks,
            'employee_status' => 'AKTIF',
        ]);

        $statusFilter = $validated['status_filter'] ?? 'all';
        $keyword = strtolower(trim((string) ($validated['q'] ?? '')));

        $allFlatRecords = collect($report['records'])
            ->flatMap(function (array $emp) use ($supervisor) {
                $subordinateKaryawan = Karyawan::where('nik', $emp['nik'])->first();
                $relationship = $subordinateKaryawan?->atasan_langsung_nik === $supervisor->nik
                    ? 'Bawahan Langsung'
                    : 'Bawahan Tidak Langsung';

                return collect($emp['days'])->map(function (array $day) use ($emp, $relationship) {
                    $statusCode = $day['status'];
                    $statusLabel = match ($statusCode) {
                        'M' => 'Hadir',
                        'A' => 'Tanpa Keterangan (Alpha)',
                        'PH' => 'Libur Nasional (PH)',
                        'EO' => 'Extra Off (EO)',
                        'C' => 'Cuti',
                        'SDC' => 'Sakit (SDC)',
                        'S' => 'Sakit',
                        'I' => 'Izin',
                        default => $day['approval_label'] ?? $statusCode
                    };

                    $isLate = false;
                    $isUnderHours = $day['is_under_daily_target'] ?? false;
                    if (! blank($day['scan_in']) && ! blank($day['scan_out'])) {
                        // Scan masuk di atas jam shift standard jika ada keterlambatan
                        if (($day['duration_minutes'] ?? 0) > 0 && $isUnderHours) {
                            $isLate = true;
                        }
                    }

                    return [
                        'date' => $day['date'],
                        'nik' => $emp['nik'],
                        'name' => $emp['name'],
                        'position' => $emp['position'],
                        'department' => $emp['department'],
                        'relationship' => $relationship,
                        'scan_in' => $day['scan_in'],
                        'scan_out' => $day['scan_out'],
                        'raw_scan_in' => $day['raw_scan_in'] ?? null,
                        'raw_scan_out' => $day['raw_scan_out'] ?? null,
                        'duration' => $day['duration_label'] ?? '-',
                        'duration_minutes' => $day['duration_minutes'] ?? 0,
                        'status_code' => $statusCode,
                        'status_label' => $statusLabel,
                        'is_late' => $isLate,
                        'needs_attention' => $day['needs_attention'] ?? false,
                        'has_incomplete_scan' => $day['has_incomplete_scan'] ?? false,
                        'is_under_daily_target' => $isUnderHours,
                        'correction' => $day['correction'] ?? null,
                    ];
                });
            })
            ->sortByDesc('date')
            ->values();

        // Hitung Summary Statistik Tim
        $totalDaysTracked = $allFlatRecords->count();
        $presentCount = $allFlatRecords->where('status_code', 'M')->count();
        $alphaCount = $allFlatRecords->where('status_code', 'A')->count();
        $lateCount = $allFlatRecords->where('is_late', true)->count();
        $leavePermissionCount = $allFlatRecords->filter(fn ($r) => in_array($r['status_code'], ['C', 'SDC', 'S', 'I', 'PH', 'EO'], true))->count();

        // Filter berdasarkan status & pencarian
        $filteredRecords = $allFlatRecords->filter(function (array $r) use ($statusFilter, $keyword) {
            if ($keyword !== '') {
                $searchContent = strtolower($r['nik'].' '.$r['name'].' '.$r['position'].' '.$r['department']);
                if (! str_contains($searchContent, $keyword)) {
                    return false;
                }
            }

            if ($statusFilter === 'present') {
                return $r['status_code'] === 'M';
            } elseif ($statusFilter === 'late') {
                return $r['is_late'] === true;
            } elseif ($statusFilter === 'leave_permission') {
                return in_array($r['status_code'], ['C', 'SDC', 'S', 'I', 'PH', 'EO'], true);
            } elseif ($statusFilter === 'alpha') {
                return $r['status_code'] === 'A';
            } elseif ($statusFilter === 'attention') {
                return $r['needs_attention'] || $r['status_code'] === 'A' || $r['has_incomplete_scan'];
            }

            return true;
        })->values();

        $page = max((int) ($validated['page'] ?? 1), 1);
        $perPage = max((int) ($validated['per_page'] ?? 15), 5);

        $paginated = $filteredRecords->forPage($page, $perPage)->values();

        return response()->json([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'supervisor' => [
                'nik' => $supervisor->nik,
                'name' => $supervisor->nama_karyawan,
                'position' => $supervisor->jabatan ?: ($supervisor->posisi ?: '-'),
            ],
            'employees' => $employees->map(fn (Karyawan $e) => [
                'nik' => $e->nik,
                'name' => $e->nama_karyawan,
                'position' => $e->jabatan ?: ($e->posisi ?: '-'),
                'department' => $e->departement ?: ($e->divisi ?: '-'),
                'relationship' => $e->atasan_langsung_nik === $supervisor->nik ? 'Bawahan Langsung' : 'Bawahan Tidak Langsung',
            ])->values(),
            'summary' => [
                'total_employees' => $employees->count(),
                'total_days_tracked' => $totalDaysTracked,
                'present_count' => $presentCount,
                'late_count' => $lateCount,
                'leave_permission_count' => $leavePermissionCount,
                'alpha_count' => $alphaCount,
            ],
            'records' => $paginated,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $filteredRecords->count(),
                'last_page' => max((int) ceil($filteredRecords->count() / $perPage), 1),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $supervisor = $this->supervisorFor($request);
        $employees = $this->manageableEmployees($supervisor);

        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'employee_nik' => ['nullable', 'string'],
        ]);

        $start = Carbon::parse($validated['start_date'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $end = Carbon::parse($validated['end_date'] ?? now()->toDateString())->startOfDay();

        $selectedEmployeeNik = $validated['employee_nik'] ?? null;
        $targetNiks = $selectedEmployeeNik
            ? $employees->where('nik', $selectedEmployeeNik)->pluck('nik')->all()
            : $employees->pluck('nik')->all();

        $report = $this->reportService->report([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'employee_niks' => $targetNiks,
            'employee_status' => 'AKTIF',
        ]);

        $records = collect($report['records'])
            ->flatMap(function (array $emp) {
                return collect($emp['days'])->map(function (array $day) use ($emp) {
                    return [
                        'date' => $day['date'],
                        'nik' => $emp['nik'],
                        'name' => $emp['name'],
                        'position' => $emp['position'],
                        'department' => $emp['department'],
                        'scan_in' => $day['scan_in'],
                        'scan_out' => $day['scan_out'],
                        'raw_scan_in' => $day['raw_scan_in'] ?? null,
                        'raw_scan_out' => $day['raw_scan_out'] ?? null,
                        'duration' => $day['duration_label'] ?? '-',
                        'duration_minutes' => $day['duration_minutes'] ?? 0,
                        'finding' => blank($day['scan_in']) && blank($day['scan_out']) ? 'Tidak ada absen' : 'Lengkap',
                        'is_resolved' => in_array($day['status'], ['M', 'C', 'PH', 'EO', 'SDC', 'S', 'I'], true),
                        'needs_attention' => $day['needs_attention'] ?? false,
                        'has_incomplete_scan' => $day['has_incomplete_scan'] ?? false,
                        'is_under_daily_target' => $day['is_under_daily_target'] ?? false,
                        'status_label' => $day['status'] === 'M' ? 'Hadir' : ($day['approval_label'] ?? $day['status']),
                        'status_code' => $day['status'],
                        'correction' => $day['correction'] ?? null,
                    ];
                });
            })
            ->sortByDesc('date')
            ->values();

        $filename = 'Rekap_Kehadiran_Tim_'.$start->format('Ymd').'_'.$end->format('Ymd').'.xlsx';

        return Excel::download(new HrAttendanceCorrectionExport($records), $filename);
    }

    private function supervisorFor(Request $request): Karyawan
    {
        $user = $request->user();
        $employee = $user?->karyawan;

        if (! $employee) {
            throw ValidationException::withMessages([
                'auth' => ['Akun Anda belum terhubung dengan data karyawan.'],
            ]);
        }

        return $employee;
    }

    private function manageableEmployees(Karyawan $supervisor): Collection
    {
        return Karyawan::query()
            ->where('nik', '!=', $supervisor->nik)
            ->where(function ($query) use ($supervisor) {
                $query->where('atasan_langsung_nik', $supervisor->nik)
                    ->orWhere('atasan_tidak_langsung_nik', $supervisor->nik);
            })
            ->where('status_karyawan', 'AKTIF')
            ->orderBy('nama_karyawan')
            ->get([
                'nik',
                'nama_karyawan',
                'jabatan',
                'posisi',
                'posisi_title',
                'departement',
                'divisi',
                'unit',
                'atasan_langsung_nik',
                'atasan_tidak_langsung_nik',
            ])
            ->values();
    }
}
