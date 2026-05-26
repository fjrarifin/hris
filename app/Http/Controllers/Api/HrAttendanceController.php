<?php

namespace App\Http\Controllers\Api;

use App\Exports\HrAttendanceExport;
use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
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
            'dates' => $report['dates'],
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

        return Excel::download(new HrAttendanceExport($report['records'], $report['dates']), $fileName);
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
        $employees = $this->selectedEmployees($departments, $employeeNiks);
        $selectedNiks = $employees->pluck('nik');
        $selectedPins = $employees->pluck('pin')->filter()->values();
        $dates = collect(CarbonPeriod::create($start, $lastDate))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->values();
        $holidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$start->toDateString(), $lastDate->toDateString()])
            ->get()
            ->keyBy(fn (PublicHoliday $holiday) => $holiday->holiday_date->toDateString());

        $attendanceDays = FingerspotAttendanceLog::query()
            ->with('karyawan')
            ->whereBetween('scan_date', [$start, $end])
            ->whereIn('pin', $selectedPins)
            ->orderBy('scan_date')
            ->get()
            ->filter(fn (FingerspotAttendanceLog $log) => $log->karyawan !== null)
            ->groupBy(fn (FingerspotAttendanceLog $log) => $log->pin.'|'.$log->scan_date->toDateString())
            ->map(function (Collection $logs): array {
                $employee = $logs->first()->karyawan;
                $scans = $this->scanSummary($logs);

                return [
                    'nik' => $employee->nik,
                    'date' => $logs->first()->scan_date->toDateString(),
                    ...$scans,
                ];
            })
            ->keyBy(fn (array $record) => $this->recordKey($record['nik'], $record['date']));

        $approvedAbsences = $this->approvedAbsenceDays($start, $lastDate, $selectedNiks);
        $records = $employees
            ->map(function (Karyawan $employee) use ($dates, $attendanceDays, $approvedAbsences, $holidays): array {
                $days = $dates->mapWithKeys(function (string $date) use (
                    $employee,
                    $attendanceDays,
                    $approvedAbsences,
                    $holidays
                ): array {
                    $key = $this->recordKey($employee->nik, $date);

                    return [
                        $date => $this->pivotDay(
                            $date,
                            $attendanceDays->get($key),
                            $approvedAbsences->get($key),
                            $holidays->get($date)
                        ),
                    ];
                });

                return $this->pivotEmployee($employee, $days);
            })
            ->values();

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $lastDate->toDateString(),
                'departments' => $departments,
                'employee_niks' => $employeeNiks,
            ],
            'dates' => $dates,
            'summary' => [
                'total_employees' => $records->count(),
                'total_attendance' => $records->sum('total_attendance'),
                'total_work_duration_minutes' => $records->sum('total_work_duration_minutes'),
                'total_present' => $records->sum('total_present'),
                'total_alpha' => $records->sum('total_alpha'),
                'leave_days' => $records->sum('total_leave'),
                'public_holiday_days' => $records->sum('total_ph'),
                'sick_days' => $records->sum('total_sick'),
                'permission_days' => $records->sum('total_permission'),
                'national_holiday_attendance' => $records->sum('total_national_holiday_attendance'),
                'national_holiday_dates' => $holidays->count(),
                'approved_absence_conflicts' => $records->sum('approved_absence_conflicts'),
            ],
            'records' => $records,
        ];
    }

    private function approvedAbsenceDays(
        Carbon $start,
        Carbon $end,
        Collection $selectedNiks
    ): Collection {
        $absences = collect();

        LeaveRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get()
            ->each(function (LeaveRequest $request) use ($absences, $start, $end, $selectedNiks): void {
                $employee = $request->user?->karyawan;
                if (! $employee || ! $selectedNiks->contains($employee->nik)) {
                    return;
                }

                $periodStart = Carbon::parse($request->start_date)->max($start);
                $periodEnd = Carbon::parse($request->end_date)->min($end);
                foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
                    $absences->put($this->recordKey($employee->nik, $date->toDateString()), [
                        'code' => 'C',
                        'label' => 'Cuti',
                        'approval_type' => 'leave',
                        'approval_id' => $request->id,
                    ]);
                }
            });

        PublicHolidayRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereBetween('claim_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (PublicHolidayRequest $request) use ($absences, $selectedNiks): void {
                $employee = $request->user?->karyawan;
                if ($employee && $selectedNiks->contains($employee->nik)) {
                    $absences->put($this->recordKey($employee->nik, $request->claim_date->toDateString()), [
                        'code' => 'PH',
                        'label' => 'Public Holiday',
                        'approval_type' => 'ph',
                        'approval_id' => $request->id,
                    ]);
                }
            });

        EmployeePermission::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (EmployeePermission $request) use ($absences, $selectedNiks): void {
                $employee = $request->user?->karyawan;
                if ($employee && $selectedNiks->contains($employee->nik)) {
                    $sick = $request->type === 'sakit';
                    $absences->put($this->recordKey($employee->nik, $request->date->toDateString()), [
                        'code' => $sick ? 'S' : 'I',
                        'label' => $sick ? 'Sakit' : 'Izin',
                        'approval_type' => 'permission',
                        'approval_id' => $request->id,
                    ]);
                }
            });

        return $absences;
    }

    private function recordKey(string $nik, string $date): string
    {
        return $nik.'|'.$date;
    }

    private function selectedEmployees(array $departments, array $employeeNiks): Collection
    {
        return Karyawan::query()
            ->when($departments !== [], fn (Builder $query) => $this->filterDepartments($query, $departments))
            ->when($employeeNiks !== [], fn (Builder $query) => $query->whereIn('nik', $employeeNiks))
            ->orderBy('nama_karyawan')
            ->get();
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

    private function pivotDay(
        string $date,
        ?array $attendance,
        ?array $absence,
        ?PublicHoliday $holiday
    ): array {
        $scanIn = $attendance['scan_in'] ?? null;
        $scanOut = $attendance['scan_out'] ?? null;
        $hasScan = $attendance !== null;
        $isHoliday = $holiday !== null;
        $status = 'A';
        $note = null;
        $hasConflict = false;

        if ($hasScan) {
            $status = 'M';
        }

        if ($absence) {
            $status = $hasScan ? 'M' : $absence['code'];
            $hasConflict = $hasScan;
            $note = $hasScan
                ? $absence['label'].' telah disetujui HRD, tetapi karyawan memiliki scan absensi.'
                : $absence['label'].' disetujui HRD.';
        }

        return [
            'date' => $date,
            'status' => $status,
            'scan_in' => $scanIn,
            'scan_out' => $scanOut,
            'duration_minutes' => $this->workDurationMinutes($scanIn, $scanOut),
            'note' => $note,
            'is_present' => $hasScan,
            'counts_as_attendance' => in_array($status, ['M', 'PH', 'C'], true),
            'is_national_holiday' => $isHoliday,
            'holiday_name' => $holiday?->name,
            'approval_type' => $absence['approval_type'] ?? null,
            'approval_id' => $absence['approval_id'] ?? null,
            'approval_label' => $absence['label'] ?? null,
            'has_approved_absence_conflict' => $hasConflict,
        ];
    }

    private function pivotEmployee(Karyawan $employee, Collection $days): array
    {
        $workDuration = $days->sum('duration_minutes');

        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'department' => $employee->departement ?: ($employee->divisi ?: '-'),
            'unit' => $employee->unit ?: '-',
            'days' => $days,
            'total_attendance' => $days->where('counts_as_attendance', true)->count(),
            'total_work_duration_minutes' => $workDuration,
            'total_work_duration' => $this->workDurationLabel($workDuration),
            'total_present' => $days->where('status', 'M')->count(),
            'total_alpha' => $days->where('status', 'A')->count(),
            'total_ph' => $days->where('status', 'PH')->count(),
            'total_leave' => $days->where('status', 'C')->count(),
            'total_sick' => $days->where('status', 'S')->count(),
            'total_permission' => $days->where('status', 'I')->count(),
            'total_national_holiday_attendance' => $days
                ->where('is_present', true)
                ->where('is_national_holiday', true)
                ->count(),
            'approved_absence_conflicts' => $days->where('has_approved_absence_conflict', true)->count(),
        ];
    }

    private function workDurationMinutes(?string $scanIn, ?string $scanOut): int
    {
        if (! $scanIn || ! $scanOut) {
            return 0;
        }

        $start = Carbon::createFromFormat('H:i:s', $scanIn);
        $end = Carbon::createFromFormat('H:i:s', $scanOut);

        return $end->gt($start) ? (int) floor($start->diffInSeconds($end) / 60) : 0;
    }

    private function workDurationLabel(int $minutes): string
    {
        return intdiv($minutes, 60).' jam '.($minutes % 60).' menit';
    }
}
