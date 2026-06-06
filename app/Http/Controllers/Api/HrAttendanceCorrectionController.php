<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Services\HrdAuditLogService;
use App\Services\HrAttendanceReportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HrAttendanceCorrectionController extends Controller
{
    public function __construct(private readonly HrAttendanceReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:100'],
            'status_filter' => ['nullable', 'string', 'in:all,alpha_only'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $start = Carbon::parse($validated['start_date'] ?? $validated['date'] ?? now()->subDay()->toDateString())->startOfDay();
        $end = Carbon::parse($validated['end_date'] ?? $validated['date'] ?? $start->toDateString())->startOfDay();

        if ($start->diffInDays($end) > 60) {
            throw ValidationException::withMessages([
                'end_date' => ['Rentang koreksi absensi maksimal 60 hari.'],
            ]);
        }

        $keyword = strtolower(trim((string) ($validated['q'] ?? '')));
        $statusFilter = $validated['status_filter'] ?? 'alpha_only';

        $report = $this->reportService->report([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);

        $records = collect($report['records'])
            ->flatMap(function (array $employee) {
                return collect($employee['days'])
                    ->filter(function (array $day) {
                        return blank($day['scan_in']) || blank($day['scan_out']);
                    })
                    ->map(function (array $day) use ($employee) {
                        $statusLabel = match ($day['status']) {
                            'M' => 'Hadir',
                            'A' => 'Tanpa Keterangan',
                            'PH' => 'Libur Nasional',
                            'EO' => 'Libur Ekstra / Off',
                            default => $day['approval_label'] ?? $day['status']
                        };

                        return [
                            'date' => $day['date'],
                            'nik' => $employee['nik'],
                            'name' => $employee['name'],
                            'position' => $employee['position'],
                            'department' => $employee['department'],
                            'scan_in' => $day['scan_in'],
                            'scan_out' => $day['scan_out'],
                            'raw_scan_in' => $day['raw_scan_in'] ?? null,
                            'raw_scan_out' => $day['raw_scan_out'] ?? null,
                            'finding' => blank($day['raw_scan_in']) && blank($day['raw_scan_out']) ? 'Tidak ada absen' : (blank($day['raw_scan_in']) ? 'Tidak scan masuk' : 'Tidak scan pulang'),
                            'is_resolved' => !blank($day['scan_in']) && !blank($day['scan_out']),
                            'status_label' => $statusLabel,
                            'status_code' => $day['status'],
                            'correction' => $day['correction'] ?? null,
                        ];
                    });
            })
            ->filter(function (array $record) use ($keyword, $statusFilter) {
                if ($statusFilter === 'alpha_only') {
                    // Only show Alpha (M) or Incomplete (H)
                    if (!in_array($record['status_code'], ['M', 'H'], true)) {
                        return false;
                    }
                }

                if ($keyword !== '') {
                    $matches = collect([
                        $record['date'],
                        $record['nik'],
                        $record['name'],
                        $record['position'],
                        $record['department'],
                        $record['status_label'],
                    ])->contains(fn ($value) => str_contains(strtolower((string) $value), $keyword));
                    if (!$matches) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $perPage = 10;
        $page = max((int) ($validated['page'] ?? 1), 1);

        return response()->json([
            'date' => $start->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'records' => $records->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $records->count(),
                'last_page' => max((int) ceil($records->count() / $perPage), 1),
            ],
        ]);
    }

    public function store(Request $request, string $nik): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'corrected_scan_in' => ['nullable', 'date_format:H:i'],
            'corrected_scan_out' => ['nullable', 'date_format:H:i'],
            'has_missing_attendance_form' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $date = Carbon::parse($validated['attendance_date'])->startOfDay();

        $report = $this->reportService->report([
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'employee_niks' => [$nik],
        ]);
        
        $employeeData = collect($report['records'])->firstWhere('nik', $nik);
        $record = $employeeData ? collect($employeeData['days'])->firstWhere('date', $date->toDateString()) : null;

        if (! $record) {
            throw ValidationException::withMessages([
                'attendance_date' => ['Data absen karyawan pada tanggal ini tidak ditemukan.'],
            ]);
        }

        if (blank($record['raw_scan_in']) && empty($validated['corrected_scan_in'])) {
            throw ValidationException::withMessages([
                'corrected_scan_in' => ['Jam masuk koreksi wajib diisi.'],
            ]);
        }

        if (blank($record['raw_scan_out']) && empty($validated['corrected_scan_out'])) {
            throw ValidationException::withMessages([
                'corrected_scan_out' => ['Jam pulang koreksi wajib diisi.'],
            ]);
        }

        $existingCorrection = AttendanceCorrection::query()
            ->where('nik', $nik)
            ->whereDate('attendance_date', $date)
            ->first();
        $beforeAudit = $existingCorrection ? app(HrdAuditLogService::class)->snapshot($existingCorrection) : null;
        $correction = AttendanceCorrection::query()->updateOrCreate(
            ['nik' => $nik, 'attendance_date' => $date->toDateString()],
            [
                'corrected_scan_in' => !blank($record['raw_scan_in']) ? null : $validated['corrected_scan_in'],
                'corrected_scan_out' => !blank($record['raw_scan_out']) ? null : $validated['corrected_scan_out'],
                'has_missing_attendance_form' => ($validated['has_missing_attendance_form'] ?? false) ? true : null,
                'notes' => $validated['notes'] ?? null,
                'corrected_by' => $request->user()->id,
            ]
        );
        app(HrdAuditLogService::class)->record(
            $request,
            'Koreksi Absensi',
            $existingCorrection ? 'updated' : 'created',
            "{$nik} - {$date->toDateString()}",
            $beforeAudit,
            $correction->fresh(),
            AttendanceCorrection::class,
            $correction->id
        );

        $freshReport = $this->reportService->report([
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'employee_niks' => [$nik],
        ]);
        $freshEmployeeData = collect($freshReport['records'])->firstWhere('nik', $nik);
        $freshDay = collect($freshEmployeeData['days'])->firstWhere('date', $date->toDateString());

        $freshStatusLabel = match ($freshDay['status']) {
            'M' => 'Hadir',
            'A' => 'Tanpa Keterangan',
            'PH' => 'Libur Nasional',
            'EO' => 'Libur Ekstra / Off',
            default => $freshDay['approval_label'] ?? $freshDay['status']
        };

        return response()->json([
            'message' => 'Koreksi absensi berhasil disimpan.',
            'data' => [
                'date' => $freshDay['date'],
                'nik' => $freshEmployeeData['nik'],
                'name' => $freshEmployeeData['name'],
                'position' => $freshEmployeeData['position'],
                'department' => $freshEmployeeData['department'],
                'scan_in' => $freshDay['scan_in'],
                'scan_out' => $freshDay['scan_out'],
                'raw_scan_in' => $freshDay['raw_scan_in'] ?? null,
                'raw_scan_out' => $freshDay['raw_scan_out'] ?? null,
                'finding' => blank($freshDay['raw_scan_in']) && blank($freshDay['raw_scan_out']) ? 'Tidak ada absen' : (blank($freshDay['raw_scan_in']) ? 'Tidak scan masuk' : 'Tidak scan pulang'),
                'is_resolved' => !blank($freshDay['scan_in']) && !blank($freshDay['scan_out']),
                'status_label' => $freshStatusLabel,
                'status_code' => $freshDay['status'],
                'correction' => $freshDay['correction'] ?? null,
            ],
        ]);
    }
}
