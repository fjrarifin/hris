<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeExtraOff;
use App\Models\ExtraOffRequest;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveAccrual;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrLeaveBalanceController extends Controller
{
    private const PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM = '2024-01-01';

    public function index(Request $request): JsonResponse
    {
        $query = Karyawan::query()
            ->with(['user']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nama_karyawan', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('jabatan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('departement')) {
            $query->where('departement', $request->input('departement'));
        }

        if ($request->filled('divisi')) {
            $query->where('divisi', $request->input('divisi'));
        }

        if ($request->filled('status_karyawan')) {
            $query->where('status_karyawan', $request->input('status_karyawan'));
        }

        $allEmployees = $query->orderBy('nama_karyawan')->get();

        // Calculate balances for all matching employees
        $calculatedData = $this->calculateBalancesForEmployees($allEmployees);

        // Filter by balance type if requested
        $balanceFilter = $request->input('balance_filter');
        $filteredData = $calculatedData->filter(function ($emp) use ($balanceFilter) {
            if ($balanceFilter === 'has_leave') {
                return $emp['leave']['remaining'] > 0;
            }
            if ($balanceFilter === 'has_ph') {
                return $emp['public_holiday']['remaining'] > 0;
            }
            if ($balanceFilter === 'has_eo') {
                return $emp['extra_off']['remaining'] > 0;
            }
            if ($balanceFilter === 'leave_empty') {
                return $emp['leave']['remaining'] <= 0;
            }
            return true;
        })->values();

        // Summary metrics
        $metrics = [
            'total_employees' => $filteredData->count(),
            'total_leave_balance' => $filteredData->sum(fn ($emp) => $emp['leave']['remaining']),
            'total_ph_balance' => $filteredData->sum(fn ($emp) => $emp['public_holiday']['remaining']),
            'total_eo_balance' => $filteredData->sum(fn ($emp) => $emp['extra_off']['remaining']),
        ];

        // Options for filter dropdowns
        $departments = Karyawan::query()
            ->whereNotNull('departement')
            ->where('departement', '!=', '')
            ->distinct()
            ->pluck('departement')
            ->sort()
            ->values();

        $divisions = Karyawan::query()
            ->whereNotNull('divisi')
            ->where('divisi', '!=', '')
            ->distinct()
            ->pluck('divisi')
            ->sort()
            ->values();

        return response()->json([
            'metrics' => $metrics,
            'departments' => $departments,
            'divisions' => $divisions,
            'data' => $filteredData,
        ]);
    }

    public function show(string $nik): JsonResponse
    {
        $employee = Karyawan::with('user')->where('nik', $nik)->firstOrFail();
        $user = $employee->user;

        $leaveAccruals = [];
        $leaveRequests = [];
        $phEligibleList = [];
        $phRequests = [];
        $eoSources = [];
        $eoRequests = [];

        if ($user) {
            // Leave Accruals
            $leaveAccruals = LeaveAccrual::query()
                ->where('user_id', $user->id)
                ->orderByDesc('year')
                ->orderByDesc('month')
                ->get()
                ->map(fn ($accrual) => [
                    'id' => $accrual->id,
                    'year' => $accrual->year,
                    'month' => $accrual->month,
                    'days' => (int) $accrual->days,
                    'accrued_at' => $accrual->accrued_at?->toDateString(),
                    'expired_at' => $accrual->expired_at?->toDateString(),
                    'is_expired' => $accrual->expired_at ? Carbon::parse($accrual->expired_at)->isPast() : false,
                ]);

            // Leave Requests
            $leaveRequests = LeaveRequest::query()
                ->where('user_id', $user->id)
                ->orderByDesc('start_date')
                ->get()
                ->map(fn ($req) => [
                    'id' => $req->id,
                    'leave_type' => $req->leave_type,
                    'leave_type_label' => LeaveRequest::LEAVE_TYPES[$req->leave_type] ?? $req->leave_type,
                    'start_date' => Carbon::parse($req->start_date)->toDateString(),
                    'end_date' => Carbon::parse($req->end_date)->toDateString(),
                    'days' => Carbon::parse($req->start_date)->diffInDays(Carbon::parse($req->end_date)) + 1,
                    'status' => $req->status,
                    'reason' => $req->reason,
                ]);

            // Public Holidays Eligible & Requests
            $phRequests = PublicHolidayRequest::with('holiday')
                ->where('user_id', $user->id)
                ->orderByDesc('claim_date')
                ->get()
                ->map(fn ($ph) => [
                    'id' => $ph->id,
                    'claim_date' => $ph->claim_date?->toDateString(),
                    'holiday_name' => $ph->holiday?->name ?? 'Hari Libur Nasional',
                    'holiday_date' => $ph->holiday?->holiday_date?->toDateString(),
                    'status' => $ph->status,
                ]);

            $eligiblePhs = $this->getEligiblePublicHolidaysForUser($user, $employee);
            $phEligibleList = $eligiblePhs->map(fn ($ph) => [
                'id' => $ph->id,
                'name' => $ph->name,
                'holiday_date' => $ph->holiday_date?->toDateString(),
                'claimed' => $phRequests->contains('holiday_name', $ph->name),
            ]);

            // Extra Off Requests
            $eoRequests = ExtraOffRequest::query()
                ->where('user_id', $user->id)
                ->orderByDesc('claim_date')
                ->get()
                ->map(fn ($eo) => [
                    'id' => $eo->id,
                    'claim_date' => $eo->claim_date?->toDateString(),
                    'source_period' => $eo->source_period_start?->format('d M Y').' - '.$eo->source_period_end?->format('d M Y'),
                    'status' => $eo->status,
                ]);
        }

        // Extra Off Sources (linked by NIK)
        $eoSources = EmployeeExtraOff::query()
            ->where('karyawan_nik', $employee->nik)
            ->orderBy('periode_start')
            ->get()
            ->map(function ($source) use ($user) {
                $used = 0;
                if ($user) {
                    $used = ExtraOffRequest::query()
                        ->where('user_id', $user->id)
                        ->whereDate('source_period_start', $source->periode_start)
                        ->whereDate('source_period_end', $source->periode_end)
                        ->whereNotIn('status', ['rejected', 'cancelled'])
                        ->count();
                }
                $remaining = max((int) $source->days - $used, 0);

                return [
                    'id' => $source->id,
                    'periode_start' => $source->periode_start?->toDateString(),
                    'periode_end' => $source->periode_end?->toDateString(),
                    'label' => $source->periode_start?->format('d M Y').' - '.$source->periode_end?->format('d M Y'),
                    'days' => (int) $source->days,
                    'used_days' => $used,
                    'remaining_days' => $remaining,
                    'source' => $source->source,
                    'notes' => $source->notes,
                ];
            });

        // Summary calculated balance
        $summary = $this->calculateBalancesForEmployees(collect([$employee]))->first();

        return response()->json([
            'employee' => [
                'nik' => $employee->nik,
                'nama_karyawan' => $employee->nama_karyawan,
                'jabatan' => $employee->jabatan,
                'posisi' => $employee->posisi,
                'departement' => $employee->departement,
                'divisi' => $employee->divisi,
                'unit' => $employee->unit,
                'status_karyawan' => $employee->status_karyawan,
                'join_date' => $employee->join_date?->toDateString(),
                'email' => $employee->email,
                'no_hp' => $employee->no_hp,
            ],
            'summary' => $summary,
            'details' => [
                'leave_accruals' => $leaveAccruals,
                'leave_requests' => $leaveRequests,
                'ph_eligible_list' => $phEligibleList,
                'ph_requests' => $phRequests,
                'extra_off_sources' => $eoSources,
                'extra_off_requests' => $eoRequests,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Karyawan::query()->with(['user']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('nama_karyawan', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%");
            });
        }
        if ($request->filled('departement')) {
            $query->where('departement', $request->input('departement'));
        }
        if ($request->filled('divisi')) {
            $query->where('divisi', $request->input('divisi'));
        }

        $allEmployees = $query->orderBy('nama_karyawan')->get();
        $data = $this->calculateBalancesForEmployees($allEmployees);

        $filename = 'sisa_jatah_karyawan_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, "\xEF\xBB\xBF");

            // CSV Header
            fputcsv($handle, [
                'NIK',
                'Nama Karyawan',
                'Departemen',
                'Divisi',
                'Jabatan',
                'Status Karyawan',
                'Tanggal Bergabung',
                'Accrued Cuti (Hari)',
                'Cuti Terpakai (Hari)',
                'Sisa Cuti Tahunan (Hari)',
                'Eligible PH (Hari)',
                'PH Terpakai (Hari)',
                'Sisa PH (Hari)',
                'Jatah Extra Off (Hari)',
                'EO Terpakai (Hari)',
                'Sisa Extra Off (Hari)',
            ]);

            foreach ($data as $emp) {
                fputcsv($handle, [
                    $emp['nik'],
                    $emp['nama_karyawan'],
                    $emp['departement'],
                    $emp['divisi'],
                    $emp['jabatan'],
                    $emp['status_karyawan'],
                    $emp['join_date'],
                    $emp['leave']['accrued'],
                    $emp['leave']['used'],
                    $emp['leave']['remaining'],
                    $emp['public_holiday']['eligible'],
                    $emp['public_holiday']['used'],
                    $emp['public_holiday']['remaining'],
                    $emp['extra_off']['granted'],
                    $emp['extra_off']['used'],
                    $emp['extra_off']['remaining'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    private function calculateBalancesForEmployees($employees)
    {
        $userIds = $employees->pluck('user.id')->filter()->values()->all();
        $niks = $employees->pluck('nik')->filter()->values()->all();
        $pins = $employees->pluck('pin')->filter()->values()->all();

        // Batch load Leave Accruals
        $accrualsGrouped = LeaveAccrual::query()
            ->whereIn('user_id', $userIds)
            ->where('is_used', false)
            ->where('expired_at', '>=', now())
            ->get()
            ->groupBy('user_id');

        // Batch load Leave Requests (annual leave days taken)
        $leaveRequestsGrouped = LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('leave_type', 'cuti_tahunan')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get()
            ->groupBy('user_id');

        // Batch load Public Holiday Requests
        $phRequestsGrouped = PublicHolidayRequest::query()
            ->whereIn('user_id', $userIds)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get()
            ->groupBy('user_id');

        // Batch load Extra Off Sources
        $eoSourcesGrouped = EmployeeExtraOff::query()
            ->whereIn('karyawan_nik', $niks)
            ->where('days', '>', 0)
            ->get()
            ->groupBy('karyawan_nik');

        // Batch load Extra Off Requests
        $eoRequestsGrouped = ExtraOffRequest::query()
            ->whereIn('user_id', $userIds)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get()
            ->groupBy('user_id');

        // Batch load Past Active Public Holidays
        $pastHolidays = PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->orderByDesc('holiday_date')
            ->get();

        // Batch load Attendance Logs for PH eligibility check
        $attendanceLogsGrouped = collect();
        if (! empty($pins)) {
            $attendanceLogsGrouped = FingerspotAttendanceLog::query()
                ->whereIn('pin', $pins)
                ->whereBetween('scan_date', [now()->subDays(90)->startOfDay(), now()->startOfDay()])
                ->get(['pin', 'scan_date'])
                ->groupBy('pin')
                ->map(fn ($logs) => $logs->pluck('scan_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->unique());
        }

        return $employees->map(function ($emp) use (
            $accrualsGrouped,
            $leaveRequestsGrouped,
            $phRequestsGrouped,
            $eoSourcesGrouped,
            $eoRequestsGrouped,
            $pastHolidays,
            $attendanceLogsGrouped
        ) {
            $user = $emp->user;
            $userId = $user?->id;
            $nik = $emp->nik;
            $pin = $emp->pin;

            // 1. Leave (Cuti Tahunan) Balance
            $accruedDays = 0;
            $usedLeaveDays = 0;
            if ($userId) {
                $accruals = $accrualsGrouped->get($userId, collect());
                $accruedDays = (int) $accruals->sum(fn ($a) => (int) ($a->days ?: 1));

                $leaveRequests = $leaveRequestsGrouped->get($userId, collect());
                $usedLeaveDays = (int) $leaveRequests->sum(
                    fn ($req) => Carbon::parse($req->start_date)->diffInDays(Carbon::parse($req->end_date)) + 1
                );
            }
            $remainingLeaveDays = max($accruedDays - $usedLeaveDays, 0);

            // 2. Public Holiday (PH) Balance
            $eligiblePhCount = 0;
            $usedPhCount = 0;
            if ($userId) {
                $userScanDates = $pin ? $attendanceLogsGrouped->get($pin, collect()) : collect();
                $eligiblePhs = $pastHolidays->filter(function ($holiday) use ($userScanDates) {
                    $holidayDate = $holiday->holiday_date;
                    $requiresAttendance = $holidayDate->gte(Carbon::parse(self::PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM));
                    return ! $requiresAttendance || $userScanDates->contains($holidayDate->toDateString());
                });
                $eligiblePhCount = $eligiblePhs->count();

                $phRequests = $phRequestsGrouped->get($userId, collect());
                $usedPhCount = $phRequests->count();
            }
            $remainingPhDays = max($eligiblePhCount - $usedPhCount, 0);

            // 3. Extra Off (EO) Balance
            $grantedEoDays = 0;
            $usedEoDays = 0;
            $remainingEoDays = 0;

            $eoSources = $eoSourcesGrouped->get($nik, collect());
            $grantedEoDays = (int) $eoSources->sum('days');

            if ($userId) {
                $eoRequests = $eoRequestsGrouped->get($userId, collect());
                $usedEoDays = $eoRequests->count();

                foreach ($eoSources as $source) {
                    $usedForSource = $eoRequests->filter(function ($req) use ($source) {
                        return Carbon::parse($req->source_period_start)->equalTo($source->periode_start)
                            && Carbon::parse($req->source_period_end)->equalTo($source->periode_end);
                    })->count();
                    $remainingEoDays += max((int) $source->days - $usedForSource, 0);
                }
            } else {
                $remainingEoDays = $grantedEoDays;
            }

            return [
                'nik' => $emp->nik,
                'nama_karyawan' => $emp->nama_karyawan,
                'jabatan' => $emp->jabatan ?? '-',
                'posisi' => $emp->posisi ?? '-',
                'departement' => $emp->departement ?? '-',
                'divisi' => $emp->divisi ?? '-',
                'unit' => $emp->unit ?? '-',
                'status_karyawan' => $emp->status_karyawan ?? '-',
                'join_date' => $emp->join_date?->toDateString() ?? '-',
                'leave' => [
                    'accrued' => $accruedDays,
                    'used' => $usedLeaveDays,
                    'remaining' => $remainingLeaveDays,
                ],
                'public_holiday' => [
                    'eligible' => $eligiblePhCount,
                    'used' => $usedPhCount,
                    'remaining' => $remainingPhDays,
                ],
                'extra_off' => [
                    'granted' => $grantedEoDays,
                    'used' => $usedEoDays,
                    'remaining' => $remainingEoDays,
                ],
            ];
        });
    }

    private function getEligiblePublicHolidaysForUser(User $user, Karyawan $employee)
    {
        $attendedDates = $employee->pin
            ? FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [now()->subDays(90)->startOfDay(), now()->startOfDay()])
                ->get(['scan_date'])
                ->pluck('scan_date')
                ->map(fn (Carbon $date) => $date->toDateString())
                ->unique()
            : collect();

        return PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->orderByDesc('holiday_date')
            ->get()
            ->filter(fn (PublicHoliday $holiday) => $holiday->holiday_date->lt(Carbon::parse(self::PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM))
                || $attendedDates->contains($holiday->holiday_date->toDateString()))
            ->values();
    }
}
