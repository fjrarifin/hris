<?php

namespace App\Services;

use App\Models\AttendanceCorrection;
use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HrAttendanceReportService
{
    private const APPROVED_PAID_ABSENCE_MINUTES = 8 * 60;

    public function report(array $filters): array
    {
        $start = Carbon::parse($filters['start_date'])->startOfDay();
        $lastDate = Carbon::parse($filters['end_date'])->startOfDay();
        $end = $lastDate->copy()->endOfDay();

        if ($start->diffInDays($lastDate) > 59) {
            throw ValidationException::withMessages([
                'end_date' => 'Periode absensi maksimal 60 hari.',
            ]);
        }

        $departments = array_values(array_unique($filters['departments'] ?? []));
        $employeeNiks = array_values(array_unique($filters['employee_niks'] ?? []));
        $employeeStatus = $filters['employee_status'] ?? null;
        $employees = $this->selectedEmployees($departments, $employeeNiks, $employeeStatus);
        $selectedNiks = $employees->pluck('nik');
        $dates = collect(CarbonPeriod::create($start, $lastDate))
            ->map(fn (Carbon $date) => $date->toDateString())
            ->values();
        $holidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereBetween('holiday_date', [$start->toDateString(), $lastDate->toDateString()])
            ->get()
            ->keyBy(fn (PublicHoliday $holiday) => $holiday->holiday_date->toDateString());
        $attendanceDays = $this->attendanceDays($start, $end, $employees);
        $this->applyCorrections($attendanceDays, $start, $lastDate, $selectedNiks);

        $approvedAbsences = $this->approvedAbsenceDays($start, $lastDate, $selectedNiks);
        $approvedOvertimes = $this->approvedOvertimeDays($start, $lastDate, $selectedNiks, $attendanceDays);
        $records = $employees->map(function (Karyawan $employee) use (
            $dates,
            $attendanceDays,
            $approvedAbsences,
            $approvedOvertimes,
            $holidays
        ): array {
            $days = $dates->mapWithKeys(function (string $date) use (
                $employee,
                $attendanceDays,
                $approvedAbsences,
                $approvedOvertimes,
                $holidays
            ): array {
                $key = $this->recordKey($employee->nik, $date);

                return [$date => $this->pivotDay(
                    $date,
                    $attendanceDays->get($key),
                    $approvedAbsences->get($key),
                    (int) $approvedOvertimes->get($key, 0),
                    $holidays->get($date)
                )];
            });

            return $this->pivotEmployee($employee, $days);
        })->values();

        return [
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $lastDate->toDateString(),
                'departments' => $departments,
                'employee_niks' => $employeeNiks,
                'employee_status' => $employeeStatus,
            ],
            'dates' => $dates,
            'summary' => [
                'total_employees' => $records->count(),
                'period_days' => $dates->count(),
                'total_attendance' => $records->sum('total_attendance'),
                'total_work_duration_minutes' => $records->sum('total_work_duration_minutes'),
                'total_overtime_minutes' => $records->sum('total_overtime_minutes'),
                'total_present' => $records->sum('total_present'),
                'total_alpha' => $records->sum('total_alpha'),
                'leave_days' => $records->sum('total_leave'),
                'public_holiday_days' => $records->sum('total_ph'),
                'extra_off_days' => $records->sum('total_eo'),
                'sick_days' => $records->sum('total_sick'),
                'sick_with_document_days' => $records->sum('total_sick_with_document'),
                'sick_without_document_days' => $records->sum('total_sick_without_document'),
                'permission_days' => $records->sum('total_permission'),
                'national_holiday_attendance' => $records->sum('total_national_holiday_attendance'),
                'national_holiday_alpha' => $records->sum('total_national_holiday_alpha'),
                'national_holiday_dates' => $holidays->count(),
                'approved_absence_conflicts' => $records->sum('approved_absence_conflicts'),
            ],
            'records' => $records,
        ];
    }

    private function attendanceDays(Carbon $start, Carbon $end, Collection $employees): Collection
    {
        return FingerspotAttendanceLog::query()
            ->with('karyawan')
            ->whereBetween('scan_date', [$start, $end])
            ->whereIn('pin', $employees->pluck('pin')->filter()->values())
            ->orderBy('scan_date')
            ->get()
            ->filter(fn (FingerspotAttendanceLog $log) => $log->karyawan !== null)
            ->groupBy(fn (FingerspotAttendanceLog $log) => $log->pin.'|'.$log->scan_date->toDateString())
            ->map(function (Collection $logs): array {
                $employee = $logs->first()->karyawan;

                return [
                    'nik' => $employee->nik,
                    'date' => $logs->first()->scan_date->toDateString(),
                    ...$this->scanSummary($logs),
                ];
            })
            ->keyBy(fn (array $record) => $this->recordKey($record['nik'], $record['date']));
    }

    private function approvedAbsenceDays(Carbon $start, Carbon $end, Collection $selectedNiks): Collection
    {
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

                foreach (CarbonPeriod::create(Carbon::parse($request->start_date)->max($start), Carbon::parse($request->end_date)->min($end)) as $date) {
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

        ExtraOffRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereBetween('claim_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (ExtraOffRequest $request) use ($absences, $selectedNiks): void {
                $employee = $request->user?->karyawan;
                if ($employee && $selectedNiks->contains($employee->nik)) {
                    $absences->put($this->recordKey($employee->nik, $request->claim_date->toDateString()), [
                        'code' => 'EO',
                        'label' => 'Extra Off',
                        'approval_type' => 'extra_off',
                        'approval_id' => $request->id,
                    ]);
                }
            });

        EmployeePermission::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereDate('date', '<=', $end)
            ->where(function (Builder $query) use ($start): void {
                $query->whereDate('end_date', '>=', $start)
                    ->orWhere(function (Builder $fallback) use ($start): void {
                        $fallback->whereNull('end_date')->whereDate('date', '>=', $start);
                    });
            })
            ->get()
            ->each(function (EmployeePermission $request) use ($absences, $selectedNiks, $start, $end): void {
                $employee = $request->user?->karyawan;
                if ($employee && $selectedNiks->contains($employee->nik)) {
                    $sick = $request->type === 'sakit';
                    $periodStart = $request->date->copy()->max($start);
                    $periodEnd = ($request->end_date ?? $request->date)->copy()->min($end);

                    foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
                        $absences->put($this->recordKey($employee->nik, $date->toDateString()), [
                            'code' => $sick ? 'S' : 'I',
                            'label' => $sick ? 'Sakit' : 'Izin',
                            'approval_type' => 'permission',
                            'approval_id' => $request->id,
                            'permission_type' => $request->type,
                            'has_document' => filled($request->document),
                        ]);
                    }
                }
            });

        return $absences;
    }

    private function applyCorrections(Collection $attendanceDays, Carbon $start, Carbon $end, Collection $selectedNiks): void
    {
        AttendanceCorrection::query()
            ->whereIn('nik', $selectedNiks)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (AttendanceCorrection $correction) use ($attendanceDays): void {
                $date = $correction->attendance_date->toDateString();
                $key = $this->recordKey($correction->nik, $date);
                $attendance = $attendanceDays->get($key, [
                    'nik' => $correction->nik,
                    'date' => $date,
                    'scan_in' => null,
                    'scan_out' => null,
                    'overtime_scan_in' => null,
                    'overtime_scan_out' => null,
                ]);
                $attendance['raw_scan_in'] = $attendance['raw_scan_in'] ?? $attendance['scan_in'];
                $attendance['raw_scan_out'] = $attendance['raw_scan_out'] ?? $attendance['scan_out'];
                $attendance['scan_in'] = $correction->corrected_scan_in ?: $attendance['scan_in'];
                $attendance['scan_out'] = $correction->corrected_scan_out ?: $attendance['scan_out'];
                $attendance['is_corrected'] = true;
                $attendance['has_missing_attendance_form'] = $correction->has_missing_attendance_form;
                $attendance['correction_notes'] = $correction->notes;
                $attendanceDays->put($key, $attendance);
            });
    }

    private function approvedOvertimeDays(Carbon $start, Carbon $end, Collection $selectedNiks, Collection $attendanceDays): Collection
    {
        $overtimeDays = collect();

        OvertimeRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->each(function (OvertimeRequest $request) use ($overtimeDays, $selectedNiks, $attendanceDays): void {
                $employee = $request->user?->karyawan;
                if (! $employee || ! $selectedNiks->contains($employee->nik)) {
                    return;
                }

                $key = $this->recordKey($employee->nik, $request->date->toDateString());
                $attendance = $attendanceDays->get($key);
                if (! $attendance || ! $attendance['overtime_scan_in'] || ! $attendance['overtime_scan_out']) {
                    return;
                }

                $scanOut = Carbon::createFromFormat('H:i:s', $attendance['overtime_scan_out']);
                $approvedEnd = Carbon::parse($request->end_time);
                if ($scanOut->lt($approvedEnd)) {
                    return;
                }

                $minutes = (int) Carbon::parse($request->start_time)->diffInMinutes($approvedEnd);
                $overtimeDays->put($key, (int) $overtimeDays->get($key, 0) + $minutes);
            });

        return $overtimeDays;
    }

    private function selectedEmployees(array $departments, array $employeeNiks, ?string $employeeStatus): Collection
    {
        return Karyawan::query()
            ->when($departments !== [], fn (Builder $query) => $this->filterDepartments($query, $departments))
            ->when($employeeNiks !== [], fn (Builder $query) => $query->whereIn('nik', $employeeNiks))
            ->when($employeeStatus, fn (Builder $query) => $query->where('status_karyawan', $employeeStatus))
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
                        $fallback->where(fn (Builder $empty) => $empty->whereNull('departement')->orWhere('departement', ''))
                            ->whereIn('divisi', $namedDepartments);
                    });
            }

            if ($withoutDepartment) {
                $method = $namedDepartments === [] ? 'where' : 'orWhere';
                $filter->{$method}(function (Builder $empty): void {
                    $empty->where(fn (Builder $department) => $department->whereNull('departement')->orWhere('departement', ''))
                        ->where(fn (Builder $division) => $division->whereNull('divisi')->orWhere('divisi', ''));
                });
            }
        });
    }

    private function scanSummary(Collection $logs): array
    {
        $hasStatus = $logs->contains(fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '1'], true));
        $scanIn = $hasStatus
            ? $logs->first(fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '0')
            : $logs->first();
        $scanOut = $hasStatus
            ? $logs->reverse()->first(fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '1')
            : ($logs->count() > 1 ? $logs->last() : null);
        $overtimeScanIn = $logs->first(fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '4'], true)) ?? $logs->first();
        $overtimeScanOut = $logs->reverse()->first(fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['1', '5'], true))
            ?? ($logs->count() > 1 ? $logs->last() : null);

        return [
            'scan_in' => $scanIn?->scan_date?->format('H:i:s'),
            'scan_out' => $scanOut?->scan_date?->format('H:i:s'),
            'overtime_scan_in' => $overtimeScanIn?->scan_date?->format('H:i:s'),
            'overtime_scan_out' => $overtimeScanOut?->scan_date?->format('H:i:s'),
        ];
    }

    private function pivotDay(string $date, ?array $attendance, ?array $absence, int $overtimeMinutes, ?PublicHoliday $holiday): array
    {
        $scanIn = $attendance['scan_in'] ?? null;
        $scanOut = $attendance['scan_out'] ?? null;
        $hasScan = $attendance !== null;
        $status = $hasScan ? 'M' : 'A';
        $hasConflict = false;

        if ($absence) {
            $status = $hasScan ? 'M' : $absence['code'];
            $hasConflict = $hasScan;
        }

        $durationMinutes = $this->workDurationMinutes($scanIn, $scanOut);
        if (! $hasScan && in_array($status, ['PH', 'C', 'EO'], true)) {
            $durationMinutes = self::APPROVED_PAID_ABSENCE_MINUTES;
        }
        if (! $hasScan && $status === 'S' && ($absence['has_document'] ?? false)) {
            $durationMinutes = self::APPROVED_PAID_ABSENCE_MINUTES;
        }

        $hasIncompleteScan = $status === 'M' && (blank($scanIn) || blank($scanOut));
        $isUnderDailyTarget = $status === 'M' && $durationMinutes < self::APPROVED_PAID_ABSENCE_MINUTES;
        $isSickWithDocument = $status === 'S' && ($absence['has_document'] ?? false);

        return [
            'date' => $date,
            'status' => $status,
            'scan_in' => $scanIn,
            'scan_out' => $scanOut,
            'raw_scan_in' => $attendance['raw_scan_in'] ?? $scanIn,
            'raw_scan_out' => $attendance['raw_scan_out'] ?? $scanOut,
            'duration_minutes' => $durationMinutes,
            'duration_label' => $this->workDurationLabel($durationMinutes),
            'overtime_minutes' => $overtimeMinutes,
            'note' => $absence ? ($hasScan
                ? $absence['label'].' telah disetujui HRD, tetapi karyawan memiliki scan absensi.'
                : $absence['label'].' disetujui HRD.') : null,
            'is_present' => $hasScan,
            'counts_as_attendance' => in_array($status, ['M', 'PH', 'C', 'EO'], true) || $isSickWithDocument,
            'has_incomplete_scan' => $hasIncompleteScan,
            'is_under_daily_target' => $isUnderDailyTarget,
            'needs_attention' => $hasIncompleteScan || $isUnderDailyTarget,
            'is_national_holiday' => $holiday !== null,
            'holiday_name' => $holiday?->name,
            'approval_type' => $absence['approval_type'] ?? null,
            'approval_id' => $absence['approval_id'] ?? null,
            'approval_label' => $absence['label'] ?? null,
            'permission_type' => $absence['permission_type'] ?? null,
            'has_document' => $absence['has_document'] ?? null,
            'has_approved_absence_conflict' => $hasConflict,
        ];
    }

    private function pivotEmployee(Karyawan $employee, Collection $days): array
    {
        $workDuration = $days->sum('duration_minutes');
        $overtimeDuration = $days->sum('overtime_minutes');

        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'department' => $employee->departement ?: ($employee->divisi ?: '-'),
            'unit' => $employee->unit ?: '-',
            'employee_status' => $employee->status_karyawan ?: '-',
            'days' => $days,
            'total_period_days' => $days->count(),
            'total_attendance' => $days->where('counts_as_attendance', true)->count(),
            'total_work_duration_minutes' => $workDuration,
            'total_work_duration' => $this->workDurationLabel($workDuration),
            'total_overtime_minutes' => $overtimeDuration,
            'total_overtime' => $this->workDurationLabel($overtimeDuration),
            'total_present' => $days->where('status', 'M')->count(),
            'total_alpha' => $days->where('status', 'A')->count() + $days
                ->where('status', 'S')
                ->where('has_document', false)
                ->count(),
            'total_ph' => $days->where('status', 'PH')->count(),
            'total_eo' => $days->where('status', 'EO')->count(),
            'total_leave' => $days->where('status', 'C')->count(),
            'total_sick' => $days->where('status', 'S')->count(),
            'total_sick_with_document' => $days
                ->where('status', 'S')
                ->where('has_document', true)
                ->count(),
            'total_sick_without_document' => $days
                ->where('status', 'S')
                ->where('has_document', false)
                ->count(),
            'total_permission' => $days->where('status', 'I')->count(),
            'total_national_holiday_attendance' => $days->where('is_present', true)->where('is_national_holiday', true)->count(),
            'total_national_holiday_alpha' => $days->where('is_present', false)->where('is_national_holiday', true)->count(),
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

    private function recordKey(string $nik, string $date): string
    {
        return $nik.'|'.$date;
    }
}
