<?php

namespace App\Http\Controllers\Api;

use App\Exports\HrAttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrAttendanceController extends Controller
{
    public function options(): JsonResponse
    {
        $employees = Karyawan::query()
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi']);

        return response()->json([
            'departments' => $employees
                ->map(fn (Karyawan $employee) => $this->employeeDepartment($employee))
                ->unique()
                ->sort()
                ->values(),
            'employees' => $employees->map(fn (Karyawan $employee) => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                'department' => $this->employeeDepartment($employee),
            ]),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', 'max:100'],
            'employee_niks' => ['nullable', 'array'],
            'employee_niks.*' => ['string', 'max:30', 'exists:m_karyawan,nik'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $report = $this->report($validated);
        $perPage = 10;
        $total = $report['records']->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min((int) ($validated['page'] ?? 1), $lastPage);

        return response()->json([
            'filters' => $report['filters'],
            'summary' => $report['summary'],
            'records' => $report['records']->forPage($page, $perPage)->values(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
                'from' => $total ? (($page - 1) * $perPage) + 1 : 0,
                'to' => min($page * $perPage, $total),
            ],
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', 'max:100'],
            'employee_niks' => ['nullable', 'array'],
            'employee_niks.*' => ['string', 'max:30', 'exists:m_karyawan,nik'],
        ]);

        $report = $this->report($validated);
        $fileName = 'Rekap_Absensi_HRD_'.$report['filters']['start_date'].'_'.$report['filters']['end_date'].'.xlsx';

        return Excel::download(new HrAttendanceExport($report['records']), $fileName);
    }

    private function report(array $validated): array
    {
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $lastDate = Carbon::parse($validated['end_date'])->startOfDay();
        $end = $lastDate->copy()->endOfDay();

        if ($start->diffInDays($lastDate) > 59) {
            throw ValidationException::withMessages([
                'end_date' => 'Periode absensi maksimal 60 hari.',
            ]);
        }

        $departments = array_values(array_unique($validated['departments'] ?? []));
        $employeeNiks = array_values(array_unique($validated['employee_niks'] ?? []));
        $selectedNiks = $this->selectedNiks($departments, $employeeNiks);
        $selectedPins = $selectedNiks === null
            ? null
            : Karyawan::query()->whereIn('nik', $selectedNiks)->whereNotNull('pin')->pluck('pin');

        $records = FingerspotAttendanceLog::query()
            ->with('karyawan')
            ->whereBetween('scan_date', [$start, $end])
            ->when($selectedPins !== null, fn (Builder $query) => $query->whereIn('pin', $selectedPins))
            ->orderBy('scan_date')
            ->get()
            ->filter(fn (FingerspotAttendanceLog $log) => $log->karyawan !== null)
            ->groupBy(fn (FingerspotAttendanceLog $log) => $log->pin.'|'.$log->scan_date->toDateString())
            ->map(function (Collection $logs): array {
                $employee = $logs->first()->karyawan;
                $scans = $this->scanSummary($logs);

                return [
                    'date' => $logs->first()->scan_date->toDateString(),
                    'nik' => $employee->nik,
                    'name' => $employee->nama_karyawan,
                    'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                    'department' => $employee->departement ?: ($employee->divisi ?: '-'),
                    'unit' => $employee->unit ?: '-',
                    ...$scans,
                    'is_complete' => $scans['scan_in'] !== null && $scans['scan_out'] !== null,
                    'requires_scan' => true,
                    'attendance_type' => 'Hadir',
                    'note' => null,
                    'approval_type' => null,
                    'approval_id' => null,
                    'has_approved_absence_conflict' => false,
                ];
            })
            ->keyBy(fn (array $record) => $this->recordKey($record['nik'], $record['date']));

        $records = $this->mergeApprovedAbsences($records, $start, $lastDate, $selectedNiks)
            ->values()
            ->sortByDesc('date')
            ->values();

        $attendanceTotals = $records->countBy('nik');
        $records = $records->map(fn (array $record) => [
            ...$record,
            'attendance_total' => $attendanceTotals[$record['nik']],
        ]);

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $lastDate->toDateString(),
                'departments' => $departments,
                'employee_niks' => $employeeNiks,
            ],
            'summary' => [
                'total_employees' => $records->pluck('nik')->unique()->count(),
                'total_attendance' => $records->count(),
                'missing_scan_in' => $records->where('requires_scan', true)->whereNull('scan_in')->count(),
                'missing_scan_out' => $records->where('requires_scan', true)->whereNull('scan_out')->count(),
                'leave_days' => $records->where('attendance_type', 'Cuti')->count(),
                'public_holiday_days' => $records->where('attendance_type', 'PH')->count(),
                'approved_absence_conflicts' => $records->where('has_approved_absence_conflict', true)->count(),
            ],
            'records' => $records,
        ];
    }

    private function mergeApprovedAbsences(
        Collection $records,
        Carbon $start,
        Carbon $end,
        ?Collection $selectedNiks
    ): Collection {
        LeaveRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->each(function (LeaveRequest $request) use ($records, $start, $end, $selectedNiks): void {
                $employee = $request->user?->karyawan;
                if (! $employee || ($selectedNiks !== null && ! $selectedNiks->contains($employee->nik))) {
                    return;
                }

                $periodStart = Carbon::parse($request->start_date)->max($start);
                $periodEnd = Carbon::parse($request->end_date)->min($end);
                foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
                    $this->mergeApprovedDay($records, $employee, $date, 'Cuti', 'leave', $request->id);
                }
            });

        PublicHolidayRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereBetween('claim_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (PublicHolidayRequest $request) use ($records, $selectedNiks): void {
                $employee = $request->user?->karyawan;
                if ($employee && ($selectedNiks === null || $selectedNiks->contains($employee->nik))) {
                    $this->mergeApprovedDay($records, $employee, $request->claim_date, 'PH', 'ph', $request->id);
                }
            });

        return $records;
    }

    private function mergeApprovedDay(
        Collection $records,
        object $employee,
        mixed $date,
        string $label,
        string $approvalType,
        int $approvalId
    ): void {
        $day = Carbon::parse($date)->toDateString();
        $key = $this->recordKey($employee->nik, $day);
        $existing = $records->get($key);

        if ($existing) {
            $existing['attendance_type'] = $label;
            $existing['note'] = "{$label} telah disetujui HRD, tetapi karyawan memiliki scan absensi. Tinjau pembatalan.";
            $existing['approval_type'] = $approvalType;
            $existing['approval_id'] = $approvalId;
            $existing['has_approved_absence_conflict'] = true;
            $records->put($key, $existing);

            return;
        }

        $records->put($key, [
            'date' => $day,
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'department' => $employee->departement ?: ($employee->divisi ?: '-'),
            'unit' => $employee->unit ?: '-',
            'scan_in' => null,
            'scan_out' => null,
            'is_complete' => true,
            'requires_scan' => false,
            'attendance_type' => $label,
            'note' => "{$label} disetujui HRD.",
            'approval_type' => $approvalType,
            'approval_id' => $approvalId,
            'has_approved_absence_conflict' => false,
        ]);
    }

    private function recordKey(string $nik, string $date): string
    {
        return $nik.'|'.$date;
    }

    private function selectedNiks(array $departments, array $employeeNiks): ?Collection
    {
        if ($departments === [] && $employeeNiks === []) {
            return null;
        }

        return Karyawan::query()
            ->when($departments !== [], fn (Builder $query) => $this->filterDepartments($query, $departments))
            ->when($employeeNiks !== [], fn (Builder $query) => $query->whereIn('nik', $employeeNiks))
            ->pluck('nik');
    }

    private function filterDepartments(Builder $query, array $departments): Builder
    {
        $withoutDepartment = in_array('Tanpa Departemen', $departments, true);
        $namedDepartments = array_values(array_diff($departments, ['Tanpa Departemen']));

        return $query->where(function (Builder $filter) use ($withoutDepartment, $namedDepartments): void {
            if ($namedDepartments !== []) {
                $filter->whereIn('departement', $namedDepartments)
                    ->orWhere(function (Builder $fallback) use ($namedDepartments): void {
                        $fallback->where(function (Builder $empty): void {
                            $empty->whereNull('departement')->orWhere('departement', '');
                        })->whereIn('divisi', $namedDepartments);
                    });
            }

            if ($withoutDepartment) {
                $method = $namedDepartments === [] ? 'where' : 'orWhere';
                $filter->{$method}(function (Builder $empty): void {
                    $empty->where(function (Builder $department): void {
                        $department->whereNull('departement')->orWhere('departement', '');
                    })->where(function (Builder $division): void {
                        $division->whereNull('divisi')->orWhere('divisi', '');
                    });
                });
            }
        });
    }

    private function employeeDepartment(Karyawan $employee): string
    {
        return $employee->departement ?: ($employee->divisi ?: 'Tanpa Departemen');
    }

    private function scanSummary(Collection $logs): array
    {
        $hasStatus = $logs->contains(fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '1'], true));

        if ($hasStatus) {
            $scanIn = $logs->first(fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '0');
            $scanOut = $logs->reverse()->first(fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '1');
        } else {
            $scanIn = $logs->first();
            $scanOut = $logs->count() > 1 ? $logs->last() : null;
        }

        return [
            'scan_in' => $scanIn?->scan_date?->format('H:i:s'),
            'scan_out' => $scanOut?->scan_date?->format('H:i:s'),
        ];
    }
}
