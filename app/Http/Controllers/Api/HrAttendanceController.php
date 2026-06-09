<?php

namespace App\Http\Controllers\Api;

use App\Exports\HrAttendanceExport;
use App\Exports\HrAttendanceMinimumExport;
use App\Http\Controllers\Controller;
use App\Http\Services\WhatsAppService;
use App\Models\AttendanceCorrection;
use App\Models\EmployeePermission;
use App\Models\ExtraOffRequest;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Notifications\MinimumAttendanceWarningNotification;
use App\Services\HrAttendanceReportService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HrAttendanceController extends Controller
{
    private const MINIMUM_MONTHLY_WORK_MINUTES = 192 * 60;

    private const IDEAL_MONTHLY_ATTENDANCE_DAYS = 25;

    private const APPROVED_PAID_ABSENCE_MINUTES = 8 * 60;

    public function __construct(private readonly HrAttendanceReportService $attendanceReportService)
    {
    }

    public function options(): JsonResponse
    {
        $employees = Karyawan::query()
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi', 'status_karyawan']);

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
                'status' => $employee->status_karyawan ?: '-',
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
            'employee_status' => ['nullable', 'in:AKTIF,NONAKTIF'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $report = $this->attendanceReportService->report($validated);
        $perPage = 10;
        $total = $report['records']->count();
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min((int) ($validated['page'] ?? 1), $lastPage);

        return response()->json([
            'filters' => $report['filters'],
            'dates' => $report['dates'],
            'summary' => $report['summary'],
            'targets' => $this->monthlyTargets(),
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
            'employee_status' => ['nullable', 'in:AKTIF,NONAKTIF'],
            'format' => ['nullable', 'in:detail,summary'],
        ]);

        $report = $this->attendanceReportService->report($validated);
        $withDailyBreakdown = ($validated['format'] ?? 'detail') === 'detail';
        $suffix = $withDailyBreakdown ? 'Detail' : 'Ringkas';
        $fileName = 'Rekap_Absensi_HRD_'.$suffix.'_'.$report['filters']['start_date'].'_'.$report['filters']['end_date'].'.xlsx';

        return Excel::download(new HrAttendanceExport($report['records'], $report['dates'], $withDailyBreakdown), $fileName);
    }

    public function minimumMonitoring(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', 'max:100'],
            'employee_niks' => ['nullable', 'array'],
            'employee_niks.*' => ['string', 'max:30', 'exists:m_karyawan,nik'],
            'employee_status' => ['nullable', 'in:AKTIF,NONAKTIF'],
            'result_status' => ['nullable', 'in:all,under,met'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'in:all'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $monitoring = $this->minimumMonitoringData($validated);
        $records = $monitoring['records'];

        $total = $records->count();
        $perPage = ($validated['per_page'] ?? null) === 'all' ? max($total, 1) : 15;
        $lastPage = max((int) ceil($total / $perPage), 1);
        $page = min((int) ($validated['page'] ?? 1), $lastPage);

        return response()->json([
            'filters' => [
                ...$monitoring['report']['filters'],
                'period_month' => $validated['period_month'],
                'period_label' => $monitoring['period_month']->translatedFormat('F Y'),
                'result_status' => $validated['result_status'] ?? 'all',
                'search' => $validated['search'] ?? '',
            ],
            'targets' => $monitoring['targets'],
            'summary' => [
                'total_employees' => $total,
                'under_target' => $records->where('status', 'under')->count(),
                'met_target' => $records->where('status', 'met')->count(),
            ],
            'records' => $records->forPage($page, $perPage)->values(),
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

    public function exportMinimumMonitoring(Request $request): BinaryFileResponse
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['string', 'max:100'],
            'employee_niks' => ['nullable', 'array'],
            'employee_niks.*' => ['string', 'max:30', 'exists:m_karyawan,nik'],
            'employee_status' => ['nullable', 'in:AKTIF,NONAKTIF'],
            'result_status' => ['nullable', 'in:all,under,met'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $monitoring = $this->minimumMonitoringData($validated);
        $fileName = 'Monitoring_Minimum_Absensi_'.$validated['period_month'].'.xlsx';

        return Excel::download(
            new HrAttendanceMinimumExport($monitoring['records'], $monitoring['targets']),
            $fileName
        );
    }

    public function notifyMinimumMonitoringEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'nik' => ['required', 'string', 'exists:m_karyawan,nik'],
        ]);

        $monitoring = $this->minimumMonitoringData([
            'period_month' => $validated['period_month'],
            'employee_niks' => [$validated['nik']],
            'result_status' => 'all',
        ]);
        $record = $monitoring['records']->firstWhere('nik', $validated['nik']);

        if (! $record) {
            throw ValidationException::withMessages([
                'nik' => 'Data karyawan tidak ditemukan pada periode ini.',
            ]);
        }

        if ($record['work_duration_diff_minutes'] >= 0) {
            throw ValidationException::withMessages([
                'nik' => 'Notifikasi hanya dapat dikirim untuk karyawan yang durasi jam kerjanya kurang.',
            ]);
        }

        $employee = Karyawan::query()->where('nik', $validated['nik'])->firstOrFail();
        $user = User::query()->where('username', $validated['nik'])->first();
        $periodLabel = $monitoring['period_month']->translatedFormat('F Y');

        $this->sendMinimumMonitoringNotification($employee, $user, $record, $periodLabel, $monitoring['targets']);

        return response()->json([
            'message' => 'Notifikasi kekurangan minimum absensi berhasil dikirim.',
        ]);
    }

    public function notifyMinimumMonitoringEmployees(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_month' => ['required', 'date_format:Y-m'],
            'employee_niks' => ['required', 'array', 'min:1'],
            'employee_niks.*' => ['string', 'exists:m_karyawan,nik'],
        ]);

        $monitoring = $this->minimumMonitoringData([
            'period_month' => $validated['period_month'],
            'employee_niks' => array_values(array_unique($validated['employee_niks'])),
            'result_status' => 'all',
        ]);
        $records = $monitoring['records']
            ->filter(fn (array $record): bool => $record['work_duration_diff_minutes'] < 0)
            ->values();
        $employees = Karyawan::query()
            ->whereIn('nik', $records->pluck('nik'))
            ->get()
            ->keyBy('nik');
        $users = User::query()
            ->whereIn('username', $records->pluck('nik'))
            ->get()
            ->keyBy('username');
        $periodLabel = $monitoring['period_month']->translatedFormat('F Y');
        $sent = 0;

        $records->each(function (array $record) use ($employees, $users, $periodLabel, $monitoring, &$sent): void {
            $employee = $employees->get($record['nik']);
            if (! $employee) {
                return;
            }

            $this->sendMinimumMonitoringNotification(
                $employee,
                $users->get($record['nik']),
                $record,
                $periodLabel,
                $monitoring['targets']
            );
            $sent++;
        });

        return response()->json([
            'message' => "Notifikasi massal berhasil dikirim ke {$sent} karyawan.",
            'sent' => $sent,
        ]);
    }

    public function monthlyMonitoring(Carbon $asOfDate): array
    {
        if ($asOfDate->day < 26) {
            return [
                'visible' => false,
                'as_of_date' => $asOfDate->toDateString(),
                'records' => [],
                ...$this->monthlyTargets(),
            ];
        }

        $report = $this->attendanceReportService->report([
            'start_date' => $asOfDate->copy()->startOfMonth()->toDateString(),
            'end_date' => $asOfDate->toDateString(),
        ]);
        $activeNiks = DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $asOfDate)
            ->whereDate('end_date', '>=', $asOfDate)
            ->pluck('nik');

        $records = $report['records']
            ->whereIn('nik', $activeNiks)
            ->map(function (array $record): array {
                $attendanceShortage = max(self::IDEAL_MONTHLY_ATTENDANCE_DAYS - $record['total_attendance'], 0);
                $minutesShortage = max(self::MINIMUM_MONTHLY_WORK_MINUTES - $record['total_work_duration_minutes'], 0);

                return [
                    'nik' => $record['nik'],
                    'name' => $record['name'],
                    'department' => $record['department'],
                    'total_attendance' => $record['total_attendance'],
                    'total_work_duration' => $record['total_work_duration'],
                    'attendance_shortage' => $attendanceShortage,
                    'work_duration_shortage_minutes' => $minutesShortage,
                    'work_duration_shortage' => $this->workDurationLabel($minutesShortage),
                ];
            })
            ->filter(fn (array $record) => $record['attendance_shortage'] > 0 || $record['work_duration_shortage_minutes'] > 0)
            ->values()
            ->all();

        return [
            'visible' => true,
            'as_of_date' => $asOfDate->toDateString(),
            'period_start' => $report['filters']['start_date'],
            'period_end' => $report['filters']['end_date'],
            'records' => $records,
            ...$this->monthlyTargets(),
        ];
    }

    private function minimumMonitoringData(array $validated): array
    {
        $periodMonth = Carbon::createFromFormat('Y-m-d', $validated['period_month'].'-01')->startOfDay();
        $periodStart = $periodMonth->copy()->subMonthNoOverflow()->day(25);
        $periodEnd = $periodMonth->copy()->day(24);
        $report = $this->attendanceReportService->report([
            ...$validated,
            'start_date' => $periodStart->toDateString(),
            'end_date' => $periodEnd->toDateString(),
        ]);
        $targets = $this->monthlyTargets();
        $records = $report['records']
            ->map(function (array $record) use ($targets): array {
                $attendanceDiff = $record['total_attendance'] - $targets['ideal_attendance_days'];
                $durationDiff = $record['total_work_duration_minutes'] - $targets['minimum_work_duration_minutes'];
                $isUnderTarget = $attendanceDiff < 0 || $durationDiff < 0;

                return [
                    'nik' => $record['nik'],
                    'name' => $record['name'],
                    'position' => $record['position'],
                    'department' => $record['department'],
                    'unit' => $record['unit'],
                    'employee_status' => $record['employee_status'],
                    'total_attendance' => $record['total_attendance'],
                    'attendance_diff' => $attendanceDiff,
                    'attendance_diff_label' => $this->signedNumberLabel($attendanceDiff, 'hari'),
                    'total_work_duration_minutes' => $record['total_work_duration_minutes'],
                    'total_work_duration' => $record['total_work_duration'],
                    'work_duration_diff_minutes' => $durationDiff,
                    'work_duration_diff' => $this->signedDurationLabel($durationDiff),
                    'total_overtime' => $record['total_overtime'],
                    'status' => $isUnderTarget ? 'under' : 'met',
                    'status_label' => $isUnderTarget ? 'Kurang' : 'Memenuhi / Lebih',
                    'can_notify' => $durationDiff < 0,
                ];
            });

        $records = $this->filterMinimumMonitoringRecords(
            $records,
            $validated['result_status'] ?? 'all',
            $validated['search'] ?? ''
        )->values();

        return [
            'period_month' => $periodMonth,
            'report' => $report,
            'targets' => $targets,
            'records' => $records,
        ];
    }

    private function filterMinimumMonitoringRecords(Collection $records, string $status, ?string $search): Collection
    {
        $keyword = trim(strtolower((string) $search));

        return $records
            ->when(
                in_array($status, ['under', 'met'], true),
                fn (Collection $items) => $items->where('status', $status)
            )
            ->when($keyword !== '', function (Collection $items) use ($keyword): Collection {
                return $items->filter(fn (array $record): bool => str_contains(strtolower((string) $record['nik']), $keyword)
                    || str_contains(strtolower((string) $record['name']), $keyword)
                    || str_contains(strtolower((string) $record['position']), $keyword)
                    || str_contains(strtolower((string) $record['department']), $keyword)
                    || str_contains(strtolower((string) $record['unit']), $keyword));
            });
    }

    private function minimumMonitoringWhatsAppMessage(array $record, string $periodLabel, array $targets): string
    {
        return "*PERINGATAN MINIMUM ABSENSI*\n\n"
            . "Halo {$record['name']},\n\n"
            . "Pada periode payroll {$periodLabel}, data absensi Anda masih kurang dari target perusahaan.\n\n"
            . "Target: {$targets['ideal_attendance_days']} hari dan {$targets['minimum_work_duration']}\n"
            . "Kehadiran Anda: {$record['total_attendance']} hari ({$record['attendance_diff_label']})\n"
            . "Durasi kerja Anda: {$record['total_work_duration']} ({$record['work_duration_diff']})\n\n"
            . "Mohon segera cek data absensi Anda dan hubungi HRD jika ada data yang perlu dikoreksi.\n\n"
            . 'Pesan ini dikirim otomatis oleh HRIS.';
    }

    private function sendMinimumMonitoringNotification(
        Karyawan $employee,
        ?User $user,
        array $record,
        string $periodLabel,
        array $targets
    ): void {
        if (! filled($employee->no_hp)) {
            return;
        }

        try {
            app(WhatsAppService::class)->sendMessage(
                $employee->no_hp,
                $this->minimumMonitoringWhatsAppMessage($record, $periodLabel, $targets)
            );
        } catch (\Throwable $exception) {
            Log::warning('Gagal mengirim notifikasi minimum absensi ke karyawan.', [
                'nik' => $employee->nik,
                'error' => $exception->getMessage(),
            ]);
        }

        $user?->notify(new MinimumAttendanceWarningNotification($record, $periodLabel, $targets));
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
        $employeeStatus = $validated['employee_status'] ?? null;
        $employees = $this->selectedEmployees($departments, $employeeNiks, $employeeStatus);
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
        $this->applyCorrections($attendanceDays, $start, $lastDate, $selectedNiks);

        $approvedAbsences = $this->approvedAbsenceDays($start, $lastDate, $selectedNiks);
        $approvedOvertimes = $this->approvedOvertimeDays($start, $lastDate, $selectedNiks, $attendanceDays);
        $records = $employees
            ->map(function (Karyawan $employee) use ($dates, $attendanceDays, $approvedAbsences, $approvedOvertimes, $holidays): array {
                $days = $dates->mapWithKeys(function (string $date) use (
                    $employee,
                    $attendanceDays,
                    $approvedAbsences,
                    $approvedOvertimes,
                    $holidays
                ): array {
                    $key = $this->recordKey($employee->nik, $date);

                    return [
                        $date => $this->pivotDay(
                            $date,
                            $attendanceDays->get($key),
                            $approvedAbsences->get($key),
                            (int) $approvedOvertimes->get($key, 0),
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
                'permission_days' => $records->sum('total_permission'),
                'national_holiday_attendance' => $records->sum('total_national_holiday_attendance'),
                'national_holiday_alpha' => $records->sum('total_national_holiday_alpha'),
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

    private function applyCorrections(
        Collection $attendanceDays,
        Carbon $start,
        Carbon $end,
        Collection $selectedNiks
    ): void {
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

                $attendance['scan_in'] = $correction->corrected_scan_in ?: $attendance['scan_in'];
                $attendance['scan_out'] = $correction->corrected_scan_out ?: $attendance['scan_out'];
                $attendance['is_corrected'] = true;
                $attendance['has_missing_attendance_form'] = $correction->has_missing_attendance_form;
                $attendance['correction_notes'] = $correction->notes;
                $attendanceDays->put($key, $attendance);
            });
    }

    private function approvedOvertimeDays(
        Carbon $start,
        Carbon $end,
        Collection $selectedNiks,
        Collection $attendanceDays
    ): Collection {
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

                $approvedStart = Carbon::parse($request->start_time);
                $minutes = (int) $approvedStart->diffInMinutes($approvedEnd);
                $overtimeDays->put($key, (int) $overtimeDays->get($key, 0) + $minutes);
            });

        return $overtimeDays;
    }

    private function recordKey(string $nik, string $date): string
    {
        return $nik.'|'.$date;
    }

    private function selectedEmployees(array $departments, array $employeeNiks, ?string $employeeStatus = null): Collection
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

        $overtimeScanIn = $logs->first(
            fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '4'], true)
        ) ?? $logs->first();
        $overtimeScanOut = $logs->reverse()->first(
            fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['1', '5'], true)
        ) ?? ($logs->count() > 1 ? $logs->last() : null);

        return [
            'scan_in' => $scanIn?->scan_date?->format('H:i:s'),
            'scan_out' => $scanOut?->scan_date?->format('H:i:s'),
            'overtime_scan_in' => $overtimeScanIn?->scan_date?->format('H:i:s'),
            'overtime_scan_out' => $overtimeScanOut?->scan_date?->format('H:i:s'),
        ];
    }

    private function pivotDay(
        string $date,
        ?array $attendance,
        ?array $absence,
        int $overtimeMinutes,
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

        $durationMinutes = $this->workDurationMinutes($scanIn, $scanOut);
        if (! $hasScan && in_array($status, ['PH', 'C', 'EO'], true)) {
            $durationMinutes = self::APPROVED_PAID_ABSENCE_MINUTES;
        }

        return [
            'date' => $date,
            'status' => $status,
            'scan_in' => $scanIn,
            'scan_out' => $scanOut,
            'duration_minutes' => $durationMinutes,
            'overtime_minutes' => $overtimeMinutes,
            'note' => $note,
            'is_present' => $hasScan,
            'counts_as_attendance' => in_array($status, ['M', 'PH', 'C', 'EO'], true),
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
            'total_alpha' => $days->where('status', 'A')->count(),
            'total_ph' => $days->where('status', 'PH')->count(),
            'total_eo' => $days->where('status', 'EO')->count(),
            'total_leave' => $days->where('status', 'C')->count(),
            'total_sick' => $days->where('status', 'S')->count(),
            'total_permission' => $days->where('status', 'I')->count(),
            'total_national_holiday_attendance' => $days
                ->where('is_present', true)
                ->where('is_national_holiday', true)
                ->count(),
            'total_national_holiday_alpha' => $days
                ->where('is_present', false)
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

    private function signedNumberLabel(int $value, string $unit): string
    {
        if ($value === 0) {
            return "0 {$unit}";
        }

        return ($value > 0 ? '+' : '').$value.' '.$unit;
    }

    private function signedDurationLabel(int $minutes): string
    {
        if ($minutes === 0) {
            return '0 jam 0 menit';
        }

        $prefix = $minutes > 0 ? '+' : '-';
        $absolute = abs($minutes);

        return $prefix.$this->workDurationLabel($absolute);
    }

    private function monthlyTargets(): array
    {
        return [
            'ideal_attendance_days' => self::IDEAL_MONTHLY_ATTENDANCE_DAYS,
            'minimum_work_duration_minutes' => self::MINIMUM_MONTHLY_WORK_MINUTES,
            'minimum_work_duration' => $this->workDurationLabel(self::MINIMUM_MONTHLY_WORK_MINUTES),
            'approved_ph_leave_daily_minutes' => self::APPROVED_PAID_ABSENCE_MINUTES,
        ];
    }
}
