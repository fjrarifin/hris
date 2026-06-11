<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceCorrection;
use App\Models\EmployeeExtraOff;
use App\Models\ExtraOffRequest;
use App\Models\HrdAuditLog;
use App\Models\Karyawan;
use App\Models\LeaveAccrual;
use App\Models\LeaveRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Services\HrAttendanceReportService;
use App\Services\HrdAuditLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HrAttendanceCorrectionController extends Controller
{
    private const PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM = '2026-05-27';

    public function __construct(private readonly HrAttendanceReportService $reportService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'q' => ['nullable', 'string', 'max:100'],
            'status_filter' => ['nullable', 'string', 'in:all,alpha_only,attention_only'],
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
        $statusFilter = $validated['status_filter'] ?? 'attention_only';

        $report = $this->reportService->report([
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'employee_status' => 'AKTIF',
        ]);

        $records = collect($report['records'])
            ->flatMap(function (array $employee) {
                return collect($employee['days'])
                    ->map(function (array $day) use ($employee) {
                        $statusLabel = match ($day['status']) {
                            'M' => 'Hadir',
                            'A' => 'Tanpa Keterangan',
                            'PH' => 'Libur Nasional',
                            'EO' => 'Libur Ekstra / Off',
                            'C' => 'Cuti',
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
                            'duration' => $day['duration_label'] ?? null,
                            'duration_minutes' => $day['duration_minutes'] ?? 0,
                            'finding' => $this->findingLabel($day),
                            'is_resolved' => in_array($day['status'], ['C', 'PH', 'EO'], true)
                                || (! blank($day['scan_in']) && ! blank($day['scan_out']) && ! ($day['needs_attention'] ?? false)),
                            'needs_attention' => $day['needs_attention'] ?? false,
                            'has_incomplete_scan' => $day['has_incomplete_scan'] ?? false,
                            'is_under_daily_target' => $day['is_under_daily_target'] ?? false,
                            'status_label' => $statusLabel,
                            'status_code' => $day['status'],
                            'correction' => $day['correction'] ?? null,
                        ];
                    })->values();
            })
            ->filter(function (array $record) use ($keyword, $statusFilter) {
                if ($statusFilter === 'attention_only') {
                    if (! $record['needs_attention'] && ! in_array($record['status_code'], ['A'], true)) {
                        return false;
                    }
                } elseif ($statusFilter === 'alpha_only') {
                    if (! in_array($record['status_code'], ['A', 'M'], true)) {
                        return false;
                    }

                    if ($record['status_code'] === 'M' && ! $record['has_incomplete_scan']) {
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
                    if (! $matches) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $perPage = 10;
        $page = max((int) ($validated['page'] ?? 1), 1);

        $pageRecords = $records
            ->forPage($page, $perPage)
            ->map(fn (array $record): array => [
                ...$record,
                'absence_options' => $this->absenceOptions($record['nik'], Carbon::parse($record['date']), $record['correction'] ?? null),
            ])
            ->values();

        return response()->json([
            'date' => $start->toDateString(),
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'records' => $pageRecords,
            'audit_logs' => $this->auditLogs($records, $start, $end, $keyword),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $records->count(),
                'last_page' => max((int) ceil($records->count() / $perPage), 1),
            ],
        ]);
    }

    private function findingLabel(array $day): string
    {
        if (blank($day['raw_scan_in'] ?? null) && blank($day['raw_scan_out'] ?? null)) {
            return 'Tidak ada absen';
        }

        if (blank($day['raw_scan_in'] ?? null)) {
            return 'Tidak scan masuk';
        }

        if (blank($day['raw_scan_out'] ?? null)) {
            return 'Tidak scan pulang';
        }

        if ($day['is_under_daily_target'] ?? false) {
            return 'Jam kerja kurang dari 8 jam';
        }

        return 'Lengkap';
    }

    private function auditLogs($records, Carbon $start, Carbon $end, string $keyword): array
    {
        $subjectLabels = $records
            ->map(fn (array $record): string => $record['nik'].' - '.$record['date'])
            ->unique()
            ->values();

        return HrdAuditLog::query()
            ->where('module', 'Koreksi Absensi')
            ->when(
                $subjectLabels->isNotEmpty(),
                fn ($query) => $query->whereIn('subject_label', $subjectLabels),
                fn ($query) => $query
                    ->whereBetween('occurred_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                    ->when($keyword !== '', fn ($search) => $search->where('subject_label', 'like', "%{$keyword}%"))
            )
            ->latest('occurred_at')
            ->limit(50)
            ->get()
            ->map(fn (HrdAuditLog $log): array => $this->serializeAuditLog($log))
            ->all();
    }

    private function serializeAuditLog(HrdAuditLog $log): array
    {
        return [
            'id' => $log->id,
            'module' => $log->module,
            'action' => $log->action,
            'subject_label' => $log->subject_label,
            'changed_by_name' => $log->actor_name ?: 'User tidak diketahui',
            'source_label' => 'HRD',
            'changes' => $this->humanReadableAuditChanges($log->changes ?? []),
            'created_at' => $log->occurred_at?->toIso8601String(),
        ];
    }

    private function humanReadableAuditChanges(array $changes): array
    {
        $labels = [
            'correction_type' => 'Jenis Koreksi',
            'corrected_scan_in' => 'Koreksi Jam Masuk',
            'corrected_scan_out' => 'Koreksi Jam Pulang',
            'has_missing_attendance_form' => 'Form Tidak Absen',
            'notes' => 'Catatan',
        ];

        return collect($changes)
            ->filter(fn (array $change): bool => array_key_exists($change['field'] ?? '', $labels))
            ->map(function (array $change) use ($labels): array {
                $field = $change['field'];

                return [
                    ...$change,
                    'label' => $labels[$field],
                    'old' => $this->auditDisplayValue($field, $change['old'] ?? null),
                    'new' => $this->auditDisplayValue($field, $change['new'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function auditDisplayValue(string $field, mixed $value): mixed
    {
        if ($field === 'correction_type') {
            return match ($value) {
                'time' => 'Koreksi Jam Absen',
                'leave' => 'Cuti',
                'public_holiday' => 'PH',
                'extra_off' => 'Extra Off',
                null, '' => '-',
                default => $value,
            };
        }

        if ($field === 'has_missing_attendance_form') {
            return in_array($value, [true, '1', 1, 'Ya'], true) ? 'Ya' : 'Tidak';
        }

        return blank($value) ? '-' : $value;
    }

    public function store(Request $request, string $nik): JsonResponse
    {
        $validated = $request->validate([
            'attendance_date' => ['required', 'date'],
            'correction_type' => ['nullable', Rule::in(['time', 'leave', 'public_holiday', 'extra_off'])],
            'corrected_scan_in' => ['nullable', 'date_format:H:i'],
            'corrected_scan_out' => ['nullable', 'date_format:H:i'],
            'public_holiday_id' => ['nullable', 'integer', 'exists:public_holidays,id'],
            'extra_off_source_period_start' => ['nullable', 'date'],
            'extra_off_source_period_end' => ['nullable', 'date', 'after_or_equal:extra_off_source_period_start'],
            'has_missing_attendance_form' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $date = Carbon::parse($validated['attendance_date'])->startOfDay();
        $correctionType = $validated['correction_type'] ?? 'time';

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

        if ($correctionType === 'time' && blank($record['raw_scan_in']) && empty($validated['corrected_scan_in'])) {
            throw ValidationException::withMessages([
                'corrected_scan_in' => ['Jam masuk koreksi wajib diisi.'],
            ]);
        }

        if ($correctionType === 'time' && blank($record['raw_scan_out']) && empty($validated['corrected_scan_out'])) {
            throw ValidationException::withMessages([
                'corrected_scan_out' => ['Jam pulang koreksi wajib diisi.'],
            ]);
        }

        $existingCorrection = AttendanceCorrection::query()
            ->where('nik', $nik)
            ->whereDate('attendance_date', $date)
            ->first();
        $beforeAudit = $existingCorrection ? app(HrdAuditLogService::class)->snapshot($existingCorrection) : null;
        $correction = DB::transaction(function () use ($request, $nik, $date, $validated, $correctionType, $existingCorrection): AttendanceCorrection {
            $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();
            $employeeUser = User::query()->where('username', $nik)->firstOrFail();
            $lockedCorrection = $existingCorrection
                ? AttendanceCorrection::query()->lockForUpdate()->find($existingCorrection->id)
                : null;

            if ($lockedCorrection) {
                $this->cancelLinkedAbsence($lockedCorrection);
            }

            $absence = $correctionType === 'time'
                ? ['type' => null, 'id' => null, 'leave_accrual_id' => null]
                : $this->createApprovedAbsenceFromCorrection($request, $employeeUser, $employee, $date, $correctionType, $validated);

            return AttendanceCorrection::query()->updateOrCreate(
                ['nik' => $nik, 'attendance_date' => $date->toDateString()],
                [
                    'correction_type' => $correctionType,
                    'corrected_scan_in' => $correctionType === 'time' && ! empty($validated['corrected_scan_in'])
                        ? $validated['corrected_scan_in']
                        : null,
                    'corrected_scan_out' => $correctionType === 'time' && ! empty($validated['corrected_scan_out'])
                        ? $validated['corrected_scan_out']
                        : null,
                    'has_missing_attendance_form' => $correctionType === 'time' && ($validated['has_missing_attendance_form'] ?? false) ? true : null,
                    'notes' => $validated['notes'] ?? null,
                    'corrected_by' => $request->user()->id,
                    'absence_type' => $absence['type'],
                    'absence_id' => $absence['id'],
                    'leave_accrual_id' => $absence['leave_accrual_id'],
                ]
            );
        });
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
            'C' => 'Cuti',
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
                'is_resolved' => in_array($freshDay['status'], ['C', 'PH', 'EO'], true)
                    || (! blank($freshDay['scan_in']) && ! blank($freshDay['scan_out']) && ! ($freshDay['needs_attention'] ?? false)),
                'needs_attention' => $freshDay['needs_attention'] ?? false,
                'has_incomplete_scan' => $freshDay['has_incomplete_scan'] ?? false,
                'is_under_daily_target' => $freshDay['is_under_daily_target'] ?? false,
                'status_label' => $freshStatusLabel,
                'status_code' => $freshDay['status'],
                'correction' => $freshDay['correction'] ?? null,
                'absence_options' => $this->absenceOptions($nik, $date, $freshDay['correction'] ?? null),
            ],
        ]);
    }

    private function absenceOptions(string $nik, Carbon $date, ?array $currentCorrection = null): array
    {
        $user = User::query()->where('username', $nik)->first();
        $employee = Karyawan::query()->where('nik', $nik)->first();

        if (! $user || ! $employee) {
            return [
                'leave_balance' => 0,
                'public_holidays' => [],
                'extra_off_sources' => [],
            ];
        }
        $currentPublicHolidayRequestId = ($currentCorrection['absence_type'] ?? null) === PublicHolidayRequest::class
            ? (int) ($currentCorrection['absence_id'] ?? 0)
            : null;
        $currentExtraOffRequestId = ($currentCorrection['absence_type'] ?? null) === ExtraOffRequest::class
            ? (int) ($currentCorrection['absence_id'] ?? 0)
            : null;

        return [
            'leave_balance' => $this->availableLeaveAccruals($user, $date)->count(),
            'public_holidays' => $this->eligiblePublicHolidays($user, $employee, $currentPublicHolidayRequestId)
                ->map(fn (PublicHoliday $holiday): array => [
                    'id' => $holiday->id,
                    'name' => $holiday->name,
                    'holiday_date' => $holiday->holiday_date?->toDateString(),
                    'label' => $holiday->name.' - '.$holiday->holiday_date?->format('d M Y'),
                ])
                ->values()
                ->all(),
            'extra_off_sources' => $this->availableExtraOffSources($user, $employee, $currentExtraOffRequestId)
                ->map(fn (array $source): array => [
                    'source_period_start' => $source['source_period_start'],
                    'source_period_end' => $source['source_period_end'],
                    'label' => $source['label'],
                    'remaining_days' => $source['remaining_days'],
                ])
                ->values()
                ->all(),
        ];
    }

    private function createApprovedAbsenceFromCorrection(
        Request $request,
        User $employeeUser,
        Karyawan $employee,
        Carbon $date,
        string $correctionType,
        array $validated
    ): array {
        $this->ensureNoAbsenceConflict($employeeUser, $date);

        return match ($correctionType) {
            'leave' => $this->createApprovedLeaveCorrection($request, $employeeUser, $employee, $date, $validated),
            'public_holiday' => $this->createApprovedPublicHolidayCorrection($request, $employeeUser, $employee, $date, $validated),
            'extra_off' => $this->createApprovedExtraOffCorrection($request, $employeeUser, $employee, $date, $validated),
            default => throw ValidationException::withMessages(['correction_type' => ['Jenis koreksi tidak valid.']]),
        };
    }

    private function createApprovedLeaveCorrection(Request $request, User $employeeUser, Karyawan $employee, Carbon $date, array $validated): array
    {
        $accrual = $this->availableLeaveAccruals($employeeUser, $date)->lockForUpdate()->first();

        if (! $accrual) {
            throw ValidationException::withMessages(['correction_type' => ['Saldo cuti karyawan tidak tersedia.']]);
        }

        $accrual->forceFill(['is_used' => true])->save();
        $leave = LeaveRequest::query()->create([
            'user_id' => $employeeUser->id,
            'leave_type' => 'lainnya',
            'start_date' => $date->toDateString(),
            'end_date' => $date->toDateString(),
            'reason' => $this->correctionReason('Cuti', $validated['notes'] ?? null),
            'status' => 'approved',
            'manager_approved_at' => now(),
            'manager_approved_by' => $request->user()?->id,
            'hr_approved_at' => now(),
            'hr_approved_by' => $request->user()?->id,
        ]);

        return [
            'type' => LeaveRequest::class,
            'id' => $leave->id,
            'leave_accrual_id' => $accrual->id,
        ];
    }

    private function createApprovedPublicHolidayCorrection(Request $request, User $employeeUser, Karyawan $employee, Carbon $date, array $validated): array
    {
        if (empty($validated['public_holiday_id'])) {
            throw ValidationException::withMessages(['public_holiday_id' => ['Pilih jatah PH yang akan dipakai.']]);
        }

        $holiday = PublicHoliday::query()->findOrFail($validated['public_holiday_id']);

        if (! $this->eligiblePublicHolidays($employeeUser, $employee)->contains('id', $holiday->id)) {
            throw ValidationException::withMessages(['public_holiday_id' => ['Jatah PH ini tidak tersedia untuk karyawan.']]);
        }

        $ph = PublicHolidayRequest::query()->create([
            'user_id' => $employeeUser->id,
            'public_holiday_id' => $holiday->id,
            'claim_date' => $date->toDateString(),
            'status' => 'approved',
            'manager_approved_at' => now(),
            'manager_approved_by' => $request->user()?->id,
            'hr_approved_at' => now(),
            'hr_approved_by' => $request->user()?->id,
            'expired_at' => $holiday->holiday_date?->copy()->addDays(90),
        ]);

        return [
            'type' => PublicHolidayRequest::class,
            'id' => $ph->id,
            'leave_accrual_id' => null,
        ];
    }

    private function createApprovedExtraOffCorrection(Request $request, User $employeeUser, Karyawan $employee, Carbon $date, array $validated): array
    {
        if (empty($validated['extra_off_source_period_start']) || empty($validated['extra_off_source_period_end'])) {
            throw ValidationException::withMessages(['extra_off_source_period_start' => ['Pilih sumber Extra Off yang akan dipakai.']]);
        }

        $source = EmployeeExtraOff::query()
            ->where('karyawan_nik', $employee->nik)
            ->whereDate('periode_start', $validated['extra_off_source_period_start'])
            ->whereDate('periode_end', $validated['extra_off_source_period_end'])
            ->first();

        if (! $source || $this->remainingExtraOffDays($employeeUser, $source) <= 0) {
            throw ValidationException::withMessages(['extra_off_source_period_start' => ['Saldo Extra Off periode ini tidak tersedia.']]);
        }

        $extraOff = ExtraOffRequest::query()->create([
            'user_id' => $employeeUser->id,
            'source_period_start' => $source->periode_start,
            'source_period_end' => $source->periode_end,
            'claim_date' => $date->toDateString(),
            'status' => 'approved',
            'manager_approved_at' => now(),
            'manager_approved_by' => $request->user()?->id,
            'hr_approved_at' => now(),
            'hr_approved_by' => $request->user()?->id,
        ]);

        return [
            'type' => ExtraOffRequest::class,
            'id' => $extraOff->id,
            'leave_accrual_id' => null,
        ];
    }

    private function cancelLinkedAbsence(AttendanceCorrection $correction): void
    {
        if ($correction->leave_accrual_id) {
            LeaveAccrual::query()
                ->whereKey($correction->leave_accrual_id)
                ->update(['is_used' => false]);
        }

        if (! $correction->absence_type || ! $correction->absence_id || ! class_exists($correction->absence_type)) {
            return;
        }

        $absence = $correction->absence_type::query()->find($correction->absence_id);
        if ($absence && in_array($absence->status, ['pending', 'approved'], true)) {
            $absence->forceFill([
                'status' => 'cancelled',
                'reject_reason' => 'Dibatalkan karena koreksi absensi HRD diubah.',
            ])->save();
        }
    }

    private function ensureNoAbsenceConflict(User $user, Carbon $date): void
    {
        if (LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists()
            || PublicHolidayRequest::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->whereDate('claim_date', $date)
                ->exists()
            || ExtraOffRequest::query()
                ->where('user_id', $user->id)
                ->whereNotIn('status', ['rejected', 'cancelled'])
                ->whereDate('claim_date', $date)
                ->exists()) {
            throw ValidationException::withMessages(['attendance_date' => ['Tanggal ini sudah memiliki CUTI/PH/EO yang aktif.']]);
        }
    }

    private function availableLeaveAccruals(User $user, Carbon $date)
    {
        return LeaveAccrual::query()
            ->where('user_id', $user->id)
            ->where('is_used', false)
            ->whereDate('expired_at', '>=', $date)
            ->orderBy('expired_at')
            ->orderBy('year')
            ->orderBy('month');
    }

    private function eligiblePublicHolidays(User $user, Karyawan $employee, ?int $currentRequestId = null)
    {
        $attendedDates = $employee->pin
            ? \App\Models\FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [now()->subDays(90)->startOfDay(), now()->startOfDay()])
                ->get(['scan_date'])
                ->pluck('scan_date')
                ->map(fn (Carbon $date): string => $date->toDateString())
                ->unique()
            : collect();
        $approvedIds = PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($currentRequestId, fn ($query) => $query->whereKeyNot($currentRequestId))
            ->pluck('public_holiday_id');

        return PublicHoliday::query()
            ->where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->whereNotIn('id', $approvedIds)
            ->orderByDesc('holiday_date')
            ->get()
            ->filter(fn (PublicHoliday $holiday): bool => $holiday->holiday_date->lt(Carbon::parse(self::PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM))
                || $attendedDates->contains($holiday->holiday_date->toDateString()))
            ->values();
    }

    private function availableExtraOffSources(User $user, Karyawan $employee, ?int $currentRequestId = null)
    {
        return EmployeeExtraOff::query()
            ->where('karyawan_nik', $employee->nik)
            ->where('days', '>', 0)
            ->orderBy('periode_start')
            ->get()
            ->map(function (EmployeeExtraOff $source) use ($user, $currentRequestId): array {
                $used = $this->usedExtraOffDays($user, $source, $currentRequestId);
                $remaining = max((int) $source->days - $used, 0);

                return [
                    'source_period_start' => $source->periode_start->toDateString(),
                    'source_period_end' => $source->periode_end->toDateString(),
                    'label' => $source->periode_start->format('d M Y').' - '.$source->periode_end->format('d M Y'),
                    'remaining_days' => $remaining,
                ];
            })
            ->filter(fn (array $source): bool => $source['remaining_days'] > 0)
            ->values();
    }

    private function remainingExtraOffDays(User $user, EmployeeExtraOff $source): int
    {
        return max((int) $source->days - $this->usedExtraOffDays($user, $source), 0);
    }

    private function usedExtraOffDays(User $user, EmployeeExtraOff $source, ?int $exceptRequestId = null): int
    {
        return ExtraOffRequest::query()
            ->where('user_id', $user->id)
            ->whereDate('source_period_start', $source->periode_start)
            ->whereDate('source_period_end', $source->periode_end)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->when($exceptRequestId, fn ($query) => $query->whereKeyNot($exceptRequestId))
            ->count();
    }

    private function correctionReason(string $type, ?string $notes): string
    {
        $reason = "Koreksi absensi HRD: {$type}";

        return filled($notes) ? $reason.' - '.$notes : $reason;
    }
}
