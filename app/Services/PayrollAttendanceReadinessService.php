<?php

namespace App\Services;

use App\Models\EmployeeDailySchedule;
use Illuminate\Support\Collection;

class PayrollAttendanceReadinessService
{
    public function __construct(
        private readonly HrAttendanceReportService $attendanceReportService,
        private readonly PayrollPeriodService $periodService
    ) {
    }

    public function audit(array $filters): array
    {
        $report = $this->attendanceReportService->report($filters);
        $schedules = EmployeeDailySchedule::query()
            ->with('category')
            ->whereIn('karyawan_nik', $report['records']->pluck('nik'))
            ->whereBetween('schedule_date', [$report['filters']['start_date'], $report['filters']['end_date']])
            ->get()
            ->keyBy(fn (EmployeeDailySchedule $schedule) => $this->recordKey(
                $schedule->karyawan_nik,
                $schedule->schedule_date->toDateString()
            ));

        $periodWorkdays = $this->periodService->workdaySummary(
            $report['filters']['start_date'],
            $report['filters']['end_date']
        );
        $records = $report['records']->map(fn (array $record) => [
            ...$this->auditEmployee(
            $record,
            $report['dates'],
            $schedules
            ),
            'periode_hari_kerja' => $periodWorkdays['workdays'],
            'period_public_holidays' => $periodWorkdays['public_holidays'],
        ])->map(fn (array $record): array => [
            ...$record,
            'extra_off_days' => max($record['total_hari_masuk'] - $record['periode_hari_kerja'], 0),
        ])->values();

        $blockerCount = $records->sum('blocker_count');

        return [
            'filters' => $report['filters'],
            'dates' => $report['dates'],
            'period_workdays' => $periodWorkdays,
            'summary' => [
                'total_employees' => $records->count(),
                'periode_hari_kerja' => $periodWorkdays['workdays'],
                'scheduled_workdays' => $records->sum('scheduled_workdays'),
                'missing_schedule_days' => $records->sum('missing_schedule_days'),
                'unresolved_workdays' => $records->sum('unresolved_workdays'),
                'incomplete_scan_days' => $records->sum('incomplete_scan_days'),
                'approved_absence_conflicts' => $records->sum('approved_absence_conflicts'),
                'total_hari_masuk' => $records->sum('total_hari_masuk'),
                'extra_off_days' => $records->sum('extra_off_days'),
                'sick_with_document_days' => $records->sum('sick_with_document_days'),
                'sick_without_document_days' => $records->sum('sick_without_document_days'),
                'overtime_minutes' => $records->sum('overtime_minutes'),
                'blocker_count' => $blockerCount,
            ],
            'can_submit' => $blockerCount === 0,
            'records' => $records,
        ];
    }

    private function auditEmployee(array $record, Collection $dates, Collection $schedules): array
    {
        $issues = collect();
        $summary = [
            'scheduled_workdays' => 0,
            'missing_schedule_days' => 0,
            'unresolved_workdays' => 0,
            'incomplete_scan_days' => 0,
            'approved_absence_conflicts' => 0,
            'sick_with_document_days' => 0,
            'sick_without_document_days' => 0,
            'overtime_minutes' => 0,
            'present_days' => 0,
            'leave_days' => 0,
            'ph_days' => 0,
            'eo_days' => 0,
            'permission_days' => 0,
            'total_hari_masuk' => 0,
            'extra_off_days' => 0,
        ];

        foreach ($dates as $date) {
            $day = $record['days']->get($date);
            $schedule = $schedules->get($this->recordKey($record['nik'], $date));
            $status = $day['status'] ?? null;

            $summary['overtime_minutes'] += (int) ($day['overtime_minutes'] ?? 0);

            if ($status === 'M') {
                $summary['present_days']++;
            } elseif ($status === 'C') {
                $summary['leave_days']++;
            } elseif ($status === 'PH') {
                $summary['ph_days']++;
            } elseif ($status === 'EO') {
                $summary['eo_days']++;
            } elseif ($status === 'I') {
                $summary['permission_days']++;
            } elseif ($status === 'S') {
                $key = ($day['has_document'] ?? false) ? 'sick_with_document_days' : 'sick_without_document_days';
                $summary[$key]++;
            }

            if (! $schedule || ! $schedule->category) {
                $summary['missing_schedule_days']++;
                $issues->push($this->issue($date, 'missing_schedule', 'Jadwal kerja belum tersedia.'));
                continue;
            }

            if (! $schedule->category->is_workday) {
                continue;
            }

            $summary['scheduled_workdays']++;

            if ($day['has_approved_absence_conflict'] ?? false) {
                $summary['approved_absence_conflicts']++;
                $issues->push($this->issue($date, 'approved_absence_conflict', 'Absence approved memiliki scan absensi.'));
            }

            if ($status === 'A') {
                $summary['unresolved_workdays']++;
                // Issue unresolved_workday dihapus karena status Alpha/TM bukan blocker.
                continue;
            }

            if ($status === 'M' && (blank($day['scan_in'] ?? null) || blank($day['scan_out'] ?? null))) {
                $summary['incomplete_scan_days']++;
                $issues->push($this->issue($date, 'incomplete_scan', 'Scan masuk atau pulang belum lengkap.'));
            }
        }

        $summary['total_hari_masuk'] = $summary['present_days']
            + $summary['sick_with_document_days']
            + $summary['ph_days']
            + $summary['eo_days']
            + $summary['leave_days'];

        return [
            'nik' => $record['nik'],
            'name' => $record['name'],
            'department' => $record['department'],
            ...$summary,
            'blocker_count' => $issues->count(),
            'can_submit' => $issues->isEmpty(),
            'issues' => $issues->values(),
        ];
    }

    private function issue(string $date, string $code, string $message): array
    {
        return compact('date', 'code', 'message');
    }

    private function recordKey(string $nik, string $date): string
    {
        return $nik.'|'.$date;
    }
}
