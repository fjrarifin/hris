<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDailySchedule;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHolidayRequest;
use App\Services\IncompleteAttendanceWhatsAppReport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class HrDashboardController extends Controller
{
    public function __construct(
        private readonly HrAttendanceController $hrAttendanceController,
        private readonly IncompleteAttendanceWhatsAppReport $incompleteAttendanceReport
    ) {}

    public function __invoke(): JsonResponse
    {
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $attendance = $this->attendanceForDate($today);
        $leaveToday = $this->leaveToday($today);
        $phToday = $this->publicHolidayToday($today);
        $permissionsToday = $this->permissionsToday($today);
        $permissionToday = $permissionsToday->where('type', 'izin')->values();
        $sickToday = $permissionsToday->where('type', 'sakit')->values();
        $overtimeToday = $this->overtimeToday($today);
        $incompleteYesterday = $this->incompleteAttendanceForDate($yesterday);
        $expiringContracts = $this->expiringContracts($today);

        return response()->json([
            'as_of_date' => $today->toDateString(),
            'summary' => [
                'total_employees' => Karyawan::query()->count(),
                'active_employees' => $this->activeEmployees($today),
                'attendance_today' => $attendance['mapped_employee_count'],
                'scan_pins_today' => $attendance['unique_pin_count'],
                'leave_today' => $leaveToday->count(),
                'public_holiday_today' => $phToday->count(),
                'permission_today' => $permissionToday->count(),
                'sick_today' => $sickToday->count(),
                'overtime_today' => $overtimeToday->count(),
                'expiring_contracts' => $expiringContracts->count(),
            ],
            'attendance' => $attendance,
            'leave_today' => $leaveToday,
            'public_holiday_today' => $phToday,
            'permission_today' => $permissionToday,
            'sick_today' => $sickToday,
            'overtime_today' => $overtimeToday,
            'yesterday_incomplete_attendance' => $incompleteYesterday,
            'expiring_contracts' => [
                'through_date' => $today->copy()->addMonthsNoOverflow(2)->toDateString(),
                'records' => $expiringContracts,
            ],
            'monthly_attendance_monitoring' => $this->hrAttendanceController->monthlyMonitoring($today),
        ]);
    }

    private function attendanceForDate(Carbon $date): array
    {
        $logsByPin = $this->logsByPin($date);
        $employeesByPin = Karyawan::query()
            ->whereNotNull('pin')
            ->whereIn('pin', $logsByPin->keys())
            ->get()
            ->keyBy(fn (Karyawan $employee) => (string) $employee->pin);

        $presentEmployees = $logsByPin
            ->map(function (Collection $logs, string $pin) use ($employeesByPin) {
                $employee = $employeesByPin->get($pin);

                return $employee ? $this->attendanceRow($employee, $logs) : null;
            })
            ->filter()
            ->values();

        $byDepartment = $presentEmployees
            ->groupBy('department')
            ->map(fn (Collection $employees, string $department) => [
                'department' => $department,
                'total' => $employees->count(),
                'employees' => $employees->values(),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'date' => $date->toDateString(),
            'unique_pin_count' => $logsByPin->count(),
            'mapped_employee_count' => $presentEmployees->count(),
            'unmapped_pin_count' => $logsByPin->count() - $presentEmployees->count(),
            'by_department' => $byDepartment,
            'managers_present' => $presentEmployees
                ->where('position_title', 'Manager')
                ->values(),
            'assistant_managers_present' => $presentEmployees
                ->where('position_title', 'Asst. Manager')
                ->values(),
            'management_present' => $presentEmployees
                ->filter(fn (array $employee): bool => in_array(
                    strtolower((string) $employee['position_title']),
                    ['manager', 'asst. manager', 'supervisor', 'spv', 'leader'],
                    true
                ))
                ->values(),
        ];
    }

    private function activeEmployees(Carbon $today): int
    {
        return DB::table('t_kontrak_karyawan')
            ->where('status_kontrak', 'AKTIF')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->distinct('nik')
            ->count('nik');
    }

    private function incompleteAttendanceForDate(Carbon $date): array
    {
        $scheduledNiks = EmployeeDailySchedule::query()
            ->join(
                'attendance_schedule_categories as categories',
                'categories.id',
                '=',
                'employee_daily_schedules.schedule_category_id'
            )
            ->whereDate('employee_daily_schedules.schedule_date', $date)
            ->where('categories.is_workday', true)
            ->pluck('employee_daily_schedules.karyawan_nik');

        $scheduledEmployees = Karyawan::query()
            ->whereIn('nik', $scheduledNiks)
            ->orderBy('nama_karyawan')
            ->get();
        $linkedEmployees = $scheduledEmployees->filter(fn (Karyawan $employee) => filled($employee->pin));

        $records = $this->incompleteAttendanceReport
            ->recordsForDate($date)
            ->map(function (array $record): array {
                return [
                    'nik' => $record['nik'],
                    'name' => $record['name'],
                    'position' => $record['position'],
                    'department' => $record['department'],
                    'scan_in' => $record['scan_in'],
                    'scan_out' => $record['scan_out'],
                    'missing_scan_in' => ! $record['scan_in'],
                    'missing_scan_out' => ! $record['scan_out'],
                    'whatsapp_notification_status' => 'Sudah diberikan notif WhatsApp',
                ];
            })
            ->values();

        return [
            'date' => $date->toDateString(),
            'scheduled_workday_count' => $scheduledEmployees->count(),
            'unlinked_pin_count' => $scheduledEmployees->count() - $linkedEmployees->count(),
            'records' => $records,
        ];
    }

    private function leaveToday(Carbon $date): Collection
    {
        return LeaveRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()
            ->map(fn (LeaveRequest $leave) => [
                ...$this->requestEmployeeRow($leave->user),
                'leave_type' => LeaveRequest::LEAVE_TYPES[$leave->leave_type] ?? $leave->leave_type,
                'start_date' => $leave->start_date,
                'end_date' => $leave->end_date,
            ])
            ->values();
    }

    private function publicHolidayToday(Carbon $date): Collection
    {
        return PublicHolidayRequest::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereDate('claim_date', $date)
            ->get()
            ->map(fn (PublicHolidayRequest $request) => [
                ...$this->requestEmployeeRow($request->user),
                'claim_date' => $request->claim_date?->toDateString(),
            ])
            ->values();
    }

    private function permissionsToday(Carbon $date): Collection
    {
        return EmployeePermission::query()
            ->with('user.karyawan')
            ->where('status', 'approved')
            ->whereNotNull('hr_approved_at')
            ->whereDate('date', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereDate('end_date', '>=', $date)
                    ->orWhere(function ($fallback) use ($date): void {
                        $fallback->whereNull('end_date')->whereDate('date', '>=', $date);
                    });
            })
            ->get()
            ->map(fn (EmployeePermission $permission) => [
                ...$this->requestEmployeeRow($permission->user),
                'type' => $permission->type,
                'date' => $permission->date?->toDateString(),
                'end_date' => ($permission->end_date ?? $permission->date)?->toDateString(),
            ])
            ->values();
    }

    private function overtimeToday(Carbon $date): Collection
    {
        return OvertimeRequest::query()
            ->with('user.karyawan')
            ->whereDate('date', $date)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->orderBy('start_time')
            ->get()
            ->map(fn (OvertimeRequest $request) => [
                'id' => $request->id,
                ...$this->requestEmployeeRow($request->user),
                'start_time' => substr((string) $request->start_time, 0, 5),
                'end_time' => substr((string) $request->end_time, 0, 5),
                'reason' => $request->reason,
                'status' => $request->status,
            ])
            ->values();
    }

    private function expiringContracts(Carbon $today): Collection
    {
        $closedStatuses = ['SELESAI', 'HABIS', 'EXPIRED', 'NONAKTIF'];

        $contracts = DB::table('t_kontrak_karyawan')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addMonthsNoOverflow(2))
            ->whereNotIn('status_kontrak', $closedStatuses)
            ->orderBy('end_date')
            ->get(['id', 'nik', 'kontrak_ke', 'end_date', 'status_kontrak']);
        $employees = Karyawan::query()
            ->whereIn('nik', $contracts->pluck('nik'))
            ->get()
            ->keyBy(fn (Karyawan $employee) => (string) $employee->nik);

        return $contracts
            ->map(function (object $contract) use ($employees, $today) {
                $employee = $employees->get((string) $contract->nik);

                if (! $employee) {
                    return null;
                }

                return [
                    'id' => $contract->id,
                    'nik' => $contract->nik,
                    'name' => $employee->nama_karyawan,
                    'department' => $employee->departement ?: ($employee->divisi ?: '-'),
                    'position' => $employee->jabatan ?: '-',
                    'contract_number' => $contract->kontrak_ke,
                    'end_date' => $contract->end_date,
                    'remaining_days' => $today->diffInDays(Carbon::parse($contract->end_date)),
                    'status' => $contract->status_kontrak,
                ];
            })
            ->filter()
            ->values();
    }

    private function logsByPin(Carbon $date): Collection
    {
        return FingerspotAttendanceLog::query()
            ->whereDate('scan_date', $date)
            ->orderBy('scan_date')
            ->get(['pin', 'scan_date', 'status_scan'])
            ->groupBy(fn (FingerspotAttendanceLog $log) => (string) $log->pin);
    }

    private function attendanceRow(Karyawan $employee, Collection $logs): array
    {
        return [
            ...$this->employeeRow($employee),
            ...$this->scanSummary($logs),
        ];
    }

    private function employeeRow(Karyawan $employee): array
    {
        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
            'position_title' => $employee->posisi_title ?: ($employee->jabatan ?: $employee->posisi),
            'department' => $employee->departement ?: ($employee->divisi ?: '-'),
        ];
    }

    private function requestEmployeeRow(?object $user): array
    {
        $employee = $user?->karyawan;

        return [
            'nik' => $employee?->nik ?? $user?->username,
            'name' => $employee?->nama_karyawan ?? $user?->name ?? '-',
            'position' => $employee?->jabatan ?: ($employee?->posisi ?: '-'),
            'department' => $employee?->departement ?: ($employee?->divisi ?: '-'),
        ];
    }

    private function scanSummary(Collection $logs): array
    {
        $hasStatusCodes = $logs->contains(fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '1'], true));

        if ($hasStatusCodes) {
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
