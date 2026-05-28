<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\ApprovalNotificationService;
use App\Models\EmployeeChangeLog;
use App\Models\EmployeeDailySchedule;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\Karyawan;
use App\Models\LeaveAccrual;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use App\Models\User;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use App\Notifications\RequestStatusNotification;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffPortalController extends Controller
{
    private const PROFILE_PHOTO_CHANGE_INTERVAL_DAYS = 30;

    private const PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM = '2026-05-27';

    public function __construct(private readonly ApprovalNotificationService $approvalNotification) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeFor($user);
        $today = now()->startOfDay();
        [$periodStart, $periodEnd] = $this->attendancePeriod($today);
        $hasSubordinates = $this->hasDirectSubordinates($user);

        $attendanceDays = $employee->pin
            ? FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [$periodStart, $periodEnd])
                ->get('scan_date')
                ->pluck('scan_date')
                ->map(fn (Carbon $date) => $date->toDateString())
                ->unique()
                ->count()
            : 0;

        return response()->json([
            'as_of_date' => $today->toDateString(),
            'employee' => $this->employeeSummary($employee, $user),
            'summary' => [
                'working_days' => $this->workingDaysSinceJoining($employee, $today),
                'attendance_days' => $attendanceDays,
                'leave_balance' => $this->leaveBalance($user),
                'public_holiday_balance' => $this->publicHolidayBalance($user),
            ],
            'attendance_period' => [
                'start' => $periodStart->toDateString(),
                'end' => $periodEnd->toDateString(),
            ],
            'has_subordinates' => $hasSubordinates,
            'subordinates_today' => $this->subordinatesToday($user, $today),
            'weekly_attendance' => $this->weeklyAttendance($employee, $today),
            'pending_subordinate_approvals' => $hasSubordinates ? $this->pendingApprovalsFor($user) : collect(),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeFor($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'photo_url' => $this->publicFileUrl($user->photo),
                ...$this->photoChangeAvailability($user),
                ...$this->contactChangeAvailability($user, $employee),
            ],
            'employee' => $employee,
        ]);
    }

    public function updateProfileContact(Request $request): JsonResponse
    {
        $user = $request->user();
        $employee = $this->employeeFor($user);
        $validated = $request->validate([
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['sometimes', 'required', 'string', 'max:30'],
        ]);

        $email = array_key_exists('email', $validated)
            ? strtolower(trim((string) $validated['email']))
            : null;
        $phone = array_key_exists('no_hp', $validated)
            ? trim((string) $validated['no_hp'])
            : null;

        $changes = [];

        if (array_key_exists('email', $validated) && $email !== (string) $user->email) {
            if ($user->email_updated_at) {
                throw ValidationException::withMessages([
                    'email' => ['Email hanya dapat diubah 1 kali. Untuk perubahan berikutnya, silakan hubungi HRD.'],
                ]);
            }

            $changes['email'] = [
                'old' => $user->email,
                'new' => $email,
            ];
        }

        if (array_key_exists('no_hp', $validated) && $phone !== (string) $employee->no_hp) {
            if ($employee->phone_updated_at) {
                throw ValidationException::withMessages([
                    'no_hp' => ['Nomor telepon hanya dapat diubah 1 kali. Untuk perubahan berikutnya, silakan hubungi HRD.'],
                ]);
            }

            $changes['no_hp'] = [
                'old' => $employee->no_hp,
                'new' => $phone,
            ];
        }

        if ($changes === []) {
            return response()->json([
                'message' => 'Tidak ada perubahan kontak yang perlu disimpan.',
                'user' => [
                    ...$this->contactChangeAvailability($user, $employee),
                ],
                'employee' => [
                    'email' => $employee->email,
                    'no_hp' => $employee->no_hp,
                ],
            ]);
        }

        [$user, $employee] = DB::transaction(function () use ($request, $changes): array {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $employee = $this->employeeFor($user);
            $now = now();
            $employeePayload = [];
            $userPayload = [];

            if (isset($changes['email'])) {
                if ($user->email_updated_at) {
                    throw ValidationException::withMessages([
                        'email' => ['Email hanya dapat diubah 1 kali. Untuk perubahan berikutnya, silakan hubungi HRD.'],
                    ]);
                }

                $userPayload['email'] = $changes['email']['new'];
                $userPayload['email_updated_at'] = $now;
                $employeePayload['email'] = $changes['email']['new'];
            }

            if (isset($changes['no_hp'])) {
                if ($employee->phone_updated_at) {
                    throw ValidationException::withMessages([
                        'no_hp' => ['Nomor telepon hanya dapat diubah 1 kali. Untuk perubahan berikutnya, silakan hubungi HRD.'],
                    ]);
                }

                $employeePayload['no_hp'] = $changes['no_hp']['new'];
                $employeePayload['phone_updated_at'] = $now;
            }

            if ($userPayload) {
                $user->update($userPayload);
            }

            if ($employeePayload) {
                $employee->update($employeePayload);
            }

            $this->recordContactChanges($employee, $changes, $user);

            return [$user->fresh(), $employee->fresh()];
        });

        return response()->json([
            'message' => 'Kontak berhasil diperbarui. Perubahan berikutnya dapat dibantu oleh HRD.',
            'user' => [
                'email' => $user->email,
                ...$this->contactChangeAvailability($user, $employee),
            ],
            'employee' => [
                'email' => $employee->email,
                'no_hp' => $employee->no_hp,
            ],
        ]);
    }

    public function profilePhoto(string $filename): StreamedResponse
    {
        $path = 'profile-photos/'.$filename;

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, $filename, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function updateProfilePhoto(Request $request): JsonResponse
    {
        $this->ensurePhotoCanBeChanged($request->user());

        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
        ], [
            'photo.max' => 'Ukuran foto profil maksimal 1 MB.',
            'photo.mimes' => 'Foto profil harus berformat PNG, JPG, atau JPEG.',
        ]);

        [$user, $oldPhoto, $path] = DB::transaction(function () use ($request): array {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $this->ensurePhotoCanBeChanged($user);

            $oldPhoto = $user->photo;
            $path = $request->file('photo')->store('profile-photos', 'public');

            $user->update([
                'photo' => $path,
                'photo_changed_at' => now(),
            ]);

            return [$user, $oldPhoto, $path];
        });

        if ($oldPhoto && $oldPhoto !== $path) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return response()->json([
            'message' => 'Foto profil berhasil diperbarui.',
            'photo_url' => $this->publicFileUrl($path),
            ...$this->photoChangeAvailability($user),
        ]);
    }

    private function photoChangeAvailability(User $user): array
    {
        $availableAt = $user->photo_changed_at?->copy()->addDays(self::PROFILE_PHOTO_CHANGE_INTERVAL_DAYS);

        return [
            'photo_changed_at' => $user->photo_changed_at?->toIso8601String(),
            'photo_change_available_at' => $availableAt?->toIso8601String(),
            'photo_change_available_label' => $availableAt?->format('d/m/Y H:i').' WIB',
            'can_change_photo' => ! $availableAt || now()->greaterThanOrEqualTo($availableAt),
        ];
    }

    private function ensurePhotoCanBeChanged(User $user): void
    {
        $availability = $this->photoChangeAvailability($user);

        if ($availability['can_change_photo']) {
            return;
        }

        throw ValidationException::withMessages([
            'photo' => [
                'Foto profil hanya dapat diganti 1 kali dalam 30 hari. Anda dapat mengganti kembali pada '.$availability['photo_change_available_label'].'.',
            ],
        ]);
    }

    private function contactChangeAvailability(User $user, Karyawan $employee): array
    {
        return [
            'email_updated_at' => $user->email_updated_at?->toIso8601String(),
            'phone_updated_at' => $employee->phone_updated_at?->toIso8601String(),
            'can_change_email' => ! $user->email_updated_at,
            'can_change_phone' => ! $employee->phone_updated_at,
        ];
    }

    private function recordContactChanges(Karyawan $employee, array $changes, User $actor): void
    {
        $labels = [
            'email' => 'Email',
            'no_hp' => 'Nomor HP',
        ];

        EmployeeChangeLog::create([
            'employee_nik' => $employee->nik,
            'changed_by_user_id' => $actor->id,
            'changed_by_name' => $actor->name,
            'source' => 'self_service',
            'changes' => collect($changes)
                ->map(fn (array $change, string $field): array => [
                    'field' => $field,
                    'label' => $labels[$field] ?? $field,
                    'old' => $change['old'] === null ? null : trim((string) $change['old']),
                    'new' => trim((string) $change['new']),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function attendance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $employee = $this->employeeFor($request->user());
        [$periodStart, $periodEnd] = $this->attendancePeriod(now()->startOfDay());
        $startDate = $validated['start_date'] ?? $validated['end_date'] ?? $periodStart->toDateString();
        $endDate = $validated['end_date'] ?? $validated['start_date'] ?? $periodEnd->toDateString();
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $records = $employee->pin
            ? FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [$start, $end])
                ->orderBy('scan_date')
                ->get(['scan_date'])
                ->groupBy(fn (FingerspotAttendanceLog $log) => $log->scan_date->toDateString())
                ->map(function ($logs, string $date): array {
                    $firstScan = $logs->first()->scan_date;
                    $lastScan = $logs->last()->scan_date;

                    return [
                        'date' => $date,
                        'scan_in' => $firstScan->format('H:i:s'),
                        'scan_out' => $logs->count() > 1 ? $lastScan->format('H:i:s') : null,
                        'total_scans' => $logs->count(),
                        'is_complete' => $logs->count() > 1,
                    ];
                })
                ->sortByDesc('date')
                ->values()
            : collect();

        return response()->json([
            'employee' => [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'pin' => $employee->pin,
            ],
            'filters' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'summary' => [
                'attendance_days' => $records->count(),
                'complete_days' => $records->where('is_complete', true)->count(),
                'incomplete_days' => $records->where('is_complete', false)->count(),
            ],
            'records' => $records,
        ]);
    }

    public function leaves(Request $request): JsonResponse
    {
        $user = $request->user();
        $accruals = $user->accruals()->orderByDesc('year')->orderByDesc('month')->get();

        return response()->json([
            'balance' => [
                'total' => $accruals->count(),
                'used' => $accruals->where('is_used', true)->count(),
                'available' => $accruals
                    ->where('is_used', false)
                    ->where('expired_at', '>=', now())
                    ->count(),
            ],
            'leave_types' => LeaveRequest::LEAVE_TYPES,
            'requests' => LeaveRequest::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'leave_type' => ['required', Rule::in(array_keys(LeaveRequest::LEAVE_TYPES))],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $directToHr = $this->requiresDirectHrApproval($user);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($start->diffInDays($end) + 1 > 5) {
            throw ValidationException::withMessages([
                'end_date' => 'Maksimal pengajuan cuti adalah 5 hari.',
            ]);
        }

        if (LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal cuti bertabrakan dengan pengajuan sebelumnya.',
            ]);
        }

        if (PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('claim_date', '>=', $start)
            ->whereDate('claim_date', '<=', $end)
            ->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal cuti bentrok dengan Hari Libur yang sudah diajukan.',
            ]);
        }

        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $start,
            'end_date' => $end,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
            ...$this->initialApprovalFields($directToHr),
        ])->load('user');

        $this->notifyInitialApprover($leave, 'CUTI', $directToHr);

        return response()->json([
            'message' => 'Pengajuan cuti berhasil dikirim.',
            'data' => $leave,
        ], 201);
    }

    public function destroyLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()->id && $leaveRequest->status === 'pending', 404);
        $leaveRequest->delete();

        return response()->json(['message' => 'Pengajuan cuti berhasil dihapus.']);
    }

    public function publicHolidays(Request $request): JsonResponse
    {
        $user = $request->user();
        $eligibleHolidays = $this->eligiblePublicHolidays($user);
        $approvedIds = PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('manager_approved_at')
            ->where('status', 'approved')
            ->pluck('public_holiday_id');

        return response()->json([
            'balance' => $this->publicHolidayBalance($user),
            'holidays' => $eligibleHolidays->whereNotIn('id', $approvedIds)->values(),
            'requests' => PublicHolidayRequest::query()
                ->with('holiday')
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function storePublicHoliday(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_holiday_id' => ['required', 'exists:public_holidays,id'],
            'claim_date' => ['required', 'date'],
        ]);

        $user = $request->user();
        $directToHr = $this->requiresDirectHrApproval($user);
        $holiday = PublicHoliday::findOrFail($data['public_holiday_id']);
        $claimDate = Carbon::parse($data['claim_date'])->startOfDay();
        $expiredAt = $holiday->holiday_date->copy()->addDays(90);

        if (! $this->eligiblePublicHolidays($user)->contains('id', $holiday->id)) {
            throw ValidationException::withMessages([
                'public_holiday_id' => 'PH tidak tersedia atau kehadiran pada hari libur nasional tersebut belum tercatat.',
            ]);
        }

        if ($claimDate->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['claim_date' => 'Tanggal pengambilan tidak boleh sebelum hari ini.']);
        }

        if ($claimDate->lt($holiday->holiday_date)) {
            throw ValidationException::withMessages(['claim_date' => 'Tanggal pengambilan tidak boleh sebelum tanggal PH.']);
        }

        if ($claimDate->gt($expiredAt)) {
            throw ValidationException::withMessages(['claim_date' => 'Tanggal pengambilan melewati masa berlaku PH.']);
        }

        if (LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $claimDate)
            ->whereDate('end_date', '>=', $claimDate)
            ->exists()) {
            throw ValidationException::withMessages(['claim_date' => 'Tanggal claim PH bentrok dengan pengajuan cuti.']);
        }

        if (PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->where('public_holiday_id', $holiday->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists()) {
            throw ValidationException::withMessages(['public_holiday_id' => 'Anda sudah pernah mengajukan PH ini.']);
        }

        if (PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereDate('claim_date', $claimDate)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists()) {
            throw ValidationException::withMessages(['claim_date' => 'Anda sudah memiliki claim PH di tanggal tersebut.']);
        }

        $ph = PublicHolidayRequest::create([
            'user_id' => $user->id,
            'public_holiday_id' => $holiday->id,
            'claim_date' => $claimDate,
            'expired_at' => $expiredAt,
            'status' => 'pending',
            ...$this->initialApprovalFields($directToHr),
        ])->load(['user', 'holiday']);

        $this->notifyInitialApprover($ph, 'PH', $directToHr);

        return response()->json([
            'message' => 'Pengajuan PH berhasil dikirim.',
            'data' => $ph,
        ], 201);
    }

    public function destroyPublicHoliday(Request $request, PublicHolidayRequest $publicHolidayRequest): JsonResponse
    {
        abort_unless(
            $publicHolidayRequest->user_id === $request->user()->id && $publicHolidayRequest->status === 'pending',
            404
        );
        $publicHolidayRequest->delete();

        return response()->json(['message' => 'Pengajuan PH berhasil dibatalkan.']);
    }

    public function permissions(Request $request): JsonResponse
    {
        return response()->json([
            'requests' => EmployeePermission::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
                ->map(fn (EmployeePermission $permission) => $this->serializePermission($permission)),
        ]);
    }

    public function storePermission(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['izin', 'sakit'])],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['required_if:type,izin', 'nullable', 'string', 'max:255'],
            'document' => ['required_if:type,sakit', 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $user = $request->user();
        $directToHr = $this->requiresDirectHrApproval($user);
        $date = Carbon::parse($data['date']);

        if (EmployeePermission::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('date', $date)
            ->exists()) {
            throw ValidationException::withMessages(['date' => 'Tanggal izin/sakit sudah pernah diajukan.']);
        }

        if (LeaveRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists()) {
            throw ValidationException::withMessages(['date' => 'Tanggal izin bentrok dengan pengajuan cuti.']);
        }

        if (PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('claim_date', $date)
            ->exists()) {
            throw ValidationException::withMessages(['date' => 'Tanggal izin bentrok dengan pengajuan Hari Libur.']);
        }

        $permission = EmployeePermission::create([
            'user_id' => $user->id,
            'type' => $data['type'],
            'date' => $date,
            'reason' => $data['reason'] ?? null,
            'document' => $request->file('document')?->store('permission-documents', 'public'),
            'status' => 'pending',
            ...$this->initialApprovalFields($directToHr),
        ])->load('user');

        $this->notifyInitialApprover($permission, strtoupper($permission->type), $directToHr);

        return response()->json([
            'message' => 'Pengajuan izin/sakit berhasil dikirim.',
            'data' => $this->serializePermission($permission),
        ], 201);
    }

    public function destroyPermission(Request $request, EmployeePermission $employeePermission): JsonResponse
    {
        abort_unless($employeePermission->user_id === $request->user()->id && $employeePermission->status === 'pending', 404);

        if ($employeePermission->document) {
            Storage::disk('public')->delete($employeePermission->document);
        }

        $employeePermission->delete();

        return response()->json(['message' => 'Pengajuan izin/sakit berhasil dihapus.']);
    }

    public function approvals(Request $request): JsonResponse
    {
        return response()->json(['requests' => $this->pendingApprovalsFor($request->user())]);
    }

    public function notifyHrAbsenceCancellation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['leave', 'ph'])],
            'id' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $model = $validated['type'] === 'ph'
            ? PublicHolidayRequest::with(['user.karyawan', 'holiday'])->findOrFail($validated['id'])
            : LeaveRequest::with('user.karyawan')->findOrFail($validated['id']);

        abort_unless($this->isDirectSubordinateRequest($request->user(), $model), 403);
        abort_unless(
            $model->status === 'approved' && $model->manager_approved_at !== null,
            422,
            'Pengajuan ini belum berstatus approved atasan.'
        );

        $employee = $model->user?->karyawan;
        abort_unless($employee, 404);

        $reason = $validated['reason'] ?: 'Karyawan tetap masuk kerja pada tanggal pengajuan.';
        $this->approvalNotification->notifyHrCancellationRequest(
            $model,
            $validated['type'] === 'ph' ? 'PH' : 'CUTI',
            $employee,
            $request->user(),
            $reason
        );

        return response()->json([
            'message' => 'Notifikasi permintaan pembatalan sudah dikirim ke HRD.',
        ]);
    }

    public function decideApproval(Request $request, string $type, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $model = match ($type) {
            'leave' => LeaveRequest::with('user')->findOrFail($id),
            'ph' => PublicHolidayRequest::with(['user', 'holiday'])->findOrFail($id),
            'permission' => EmployeePermission::with('user')->findOrFail($id),
            default => abort(404),
        };

        abort_unless($this->isDirectSubordinateRequest($request->user(), $model), 403);
        abort_unless($model->status === 'pending' && ! $model->manager_approved_at, 422, 'Pengajuan sudah diproses.');

        $approved = $data['decision'] === 'approved';
        if ($type === 'ph' && $approved && ! $this->hasWorkedOnPublicHoliday($model->user, $model->holiday)) {
            throw ValidationException::withMessages([
                'decision' => 'PH tidak dapat disetujui karena karyawan tidak memiliki scan pada hari libur nasional tersebut.',
            ]);
        }

        $model->update([
            'status' => $approved ? 'approved' : 'rejected',
            'manager_approved_at' => now(),
            'manager_approved_by' => $request->user()->id,
            'reject_reason' => $approved ? null : ($data['reason'] ?? null),
        ]);

        $notificationType = match ($type) {
            'leave' => 'CUTI',
            'ph' => 'PH',
            default => 'IZIN',
        };
        $this->approvalNotification->notifyIndirectManagerOfDirectManagerDecision($model, $notificationType, $data['decision']);

        if ($approved) {
            $this->approvalNotification->notifyHrGroups($model, $notificationType);
        }

        match ($type) {
            'leave' => $model->user->notify(new LeaveStatusNotification($model, $data['decision'], $data['reason'] ?? null)),
            'ph' => $model->user->notify(new PublicHolidayStatusNotification($model, $data['decision'])),
            default => $model->user->notify(new RequestStatusNotification($model, 'IZIN', $data['decision'])),
        };

        return response()->json(['message' => 'Keputusan pengajuan berhasil disimpan.']);
    }

    public function overtime(Request $request): JsonResponse
    {
        $user = $request->user();
        $subordinates = Karyawan::query()
            ->whereIn('nik', $this->subordinateNiks($user))
            ->orderBy('nama_karyawan')
            ->get(['nik', 'nama_karyawan', 'jabatan', 'departement']);

        return response()->json([
            'subordinates' => $subordinates,
            'requests' => OvertimeRequest::query()
                ->with('user.karyawan')
                ->where('requested_by_user_id', $user->id)
                ->latest()
                ->get(),
        ]);
    }

    public function storeOvertime(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_niks' => ['required', 'array', 'min:1'],
            'employee_niks.*' => ['string', 'exists:m_karyawan,nik'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $duration = Carbon::createFromFormat('H:i', $data['start_time'])
            ->diffInMinutes(Carbon::createFromFormat('H:i', $data['end_time']));

        if ($duration < 60 || $duration > 240) {
            throw ValidationException::withMessages([
                'end_time' => 'Durasi lembur harus antara 1 sampai 4 jam.',
            ]);
        }

        $manager = $request->user();
        $employeeNiks = collect($data['employee_niks'])->unique()->values();

        if ($employeeNiks->diff($this->subordinateNiks($manager))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'employee_niks' => 'Karyawan yang dipilih harus bawahan langsung Anda.',
            ]);
        }

        $employeeUserIds = $employeeNiks->map(fn (string $nik) => $this->ensureUserForEmployee($nik)->id);

        if (OvertimeRequest::query()
            ->whereIn('user_id', $employeeUserIds)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('date', $data['date'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists()) {
            throw ValidationException::withMessages([
                'start_time' => 'Salah satu karyawan sudah punya pengajuan lembur pada rentang jam tersebut.',
            ]);
        }

        $created = $employeeUserIds->map(fn (int $userId) => OvertimeRequest::create([
            'user_id' => $userId,
            'requested_by_user_id' => $manager->id,
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'reason' => $data['reason'],
            'status' => 'pending',
        ])->load('user'));

        $created->each(fn (OvertimeRequest $overtime) => $this->approvalNotification->notifyHrGroups($overtime, 'LEMBUR'));

        return response()->json([
            'message' => 'Pengajuan lembur berhasil dikirim ke HR.',
            'data' => $created,
        ], 201);
    }

    public function destroyOvertime(Request $request, OvertimeRequest $overtimeRequest): JsonResponse
    {
        abort_unless(
            $overtimeRequest->requested_by_user_id === $request->user()->id && $overtimeRequest->status === 'pending',
            404
        );
        $overtimeRequest->delete();

        return response()->json(['message' => 'Pengajuan lembur berhasil dihapus.']);
    }

    private function employeeFor(User $user): Karyawan
    {
        return Karyawan::query()->where('nik', $user->username)->firstOrFail();
    }

    private function employeeSummary(Karyawan $employee, User $user): array
    {
        return [
            'nik' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'position' => $employee->jabatan ?: $employee->posisi,
            'department' => $employee->departement ?: $employee->divisi,
            'join_date' => $employee->join_date?->toDateString(),
            'photo_url' => $this->publicFileUrl($user->photo),
        ];
    }

    private function publicFileUrl(?string $path): ?string
    {
        return $path
            ? route('profile-photos.show', ['filename' => basename($path)])
            : null;
    }

    private function workingDaysSinceJoining(Karyawan $employee, Carbon $today): int
    {
        if (! $employee->join_date || $employee->join_date->gt($today)) {
            return 0;
        }

        $days = 0;
        for ($date = $employee->join_date->copy()->startOfDay(); $date->lte($today); $date->addDay()) {
            if (! $date->isWeekend()) {
                $days++;
            }
        }

        return $days;
    }

    private function attendancePeriod(Carbon $today): array
    {
        $start = $today->day >= 25
            ? $today->copy()->day(25)
            : $today->copy()->subMonthNoOverflow()->day(25);

        return [$start->startOfDay(), $start->copy()->addMonthNoOverflow()->day(24)->endOfDay()];
    }

    private function leaveBalance(User $user): int
    {
        return LeaveAccrual::query()
            ->where('user_id', $user->id)
            ->where('is_used', false)
            ->where('expired_at', '>=', now())
            ->count();
    }

    private function publicHolidayBalance(User $user): int
    {
        $approvedIds = PublicHolidayRequest::query()
            ->where('user_id', $user->id)
            ->whereNotNull('manager_approved_at')
            ->where('status', 'approved')
            ->pluck('public_holiday_id');

        return $this->eligiblePublicHolidays($user)
            ->whereNotIn('id', $approvedIds)
            ->count();
    }

    private function eligiblePublicHolidays(User $user): Collection
    {
        $employee = $this->employeeFor($user);
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
            ->filter(fn (PublicHoliday $holiday) => ! $this->requiresAttendanceForPublicHoliday($holiday)
                || $attendedDates->contains($holiday->holiday_date->toDateString()))
            ->values();
    }

    private function requiresAttendanceForPublicHoliday(PublicHoliday $holiday): bool
    {
        return $holiday->holiday_date->gte(Carbon::parse(self::PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM));
    }

    private function hasWorkedOnPublicHoliday(User $user, PublicHoliday $holiday): bool
    {
        $employee = $this->employeeFor($user);

        return $holiday->is_active
            && (! $this->requiresAttendanceForPublicHoliday($holiday)
                || ($employee->pin
                    && FingerspotAttendanceLog::query()
                        ->where('pin', $employee->pin)
                        ->whereDate('scan_date', $holiday->holiday_date)
                        ->exists()));
    }

    private function subordinateNiks(User $user)
    {
        $employee = Karyawan::query()->where('nik', $user->username)->first();

        return $employee
            ? Karyawan::query()->where('nama_atasan_langsung', $employee->nama_karyawan)->pluck('nik')
            : collect();
    }

    private function subordinatesToday(User $user, Carbon $date): Collection
    {
        $subordinateNiks = $this->subordinateNiks($user);
        $subordinates = Karyawan::query()
            ->whereIn('nik', $subordinateNiks)
            ->orderBy('nama_karyawan')
            ->get(['nik', 'pin', 'nama_karyawan', 'jabatan', 'posisi', 'departement', 'divisi', 'unit']);
        $logsByPin = FingerspotAttendanceLog::query()
            ->whereIn('pin', $subordinates->pluck('pin')->filter())
            ->whereDate('scan_date', $date)
            ->orderBy('scan_date')
            ->get(['pin', 'scan_date', 'status_scan'])
            ->groupBy(fn (FingerspotAttendanceLog $log) => (string) $log->pin);
        $schedules = EmployeeDailySchedule::query()
            ->with('category')
            ->whereIn('karyawan_nik', $subordinateNiks)
            ->whereDate('schedule_date', $date)
            ->get()
            ->keyBy('karyawan_nik');
        $approvedAbsences = $this->subordinateApprovedAbsencesToday($subordinateNiks, $date);

        return $subordinates->map(function (Karyawan $employee) use ($logsByPin, $schedules, $approvedAbsences): array {
            $scans = $this->dailyScanSummary($logsByPin->get((string) $employee->pin, collect()));
            $schedule = $schedules->get($employee->nik);
            $approvedAbsence = $approvedAbsences->get($employee->nik);
            $hasScan = filled($scans['scan_in']) || filled($scans['scan_out']);
            $attendanceStatus = match (true) {
                $scans['scan_in'] && $scans['scan_out'] => 'checked_out',
                $approvedAbsence !== null => $approvedAbsence['status'],
                $this->isOffSchedule($schedule) => 'off',
                $scans['scan_in'] => 'working',
                $scans['scan_out'] => 'missing_in',
                default => 'absent',
            };

            return [
                'nik' => $employee->nik,
                'name' => $employee->nama_karyawan,
                'position' => $employee->jabatan ?: ($employee->posisi ?: '-'),
                'department' => $employee->departement ?: ($employee->divisi ?: '-'),
                'unit' => $employee->unit ?: '-',
                'scan_in' => $scans['scan_in'],
                'scan_out' => $scans['scan_out'],
                'attendance_status' => $attendanceStatus,
                'attendance_status_label' => $approvedAbsence['label'] ?? null,
                'schedule_code' => $schedule?->schedule_code,
                'schedule_label' => $schedule?->category?->name,
                'status_actions' => $this->subordinateStatusActions($employee, $approvedAbsence, $schedule, $hasScan),
            ];
        })->values();
    }

    private function subordinateStatusActions(
        Karyawan $employee,
        ?array $approvedAbsence,
        ?EmployeeDailySchedule $schedule,
        bool $hasScan
    ): array {
        if (! $hasScan) {
            return [];
        }

        $actions = [];
        if ($approvedAbsence && in_array($approvedAbsence['status'], ['leave', 'ph'], true)) {
            $actions[] = [
                'key' => 'notify_hr_'.$approvedAbsence['approval_type'].'_'.$approvedAbsence['approval_id'],
                'type' => 'notify_hr_cancellation',
                'label' => 'Notif HRD Batalkan '.($approvedAbsence['status'] === 'ph' ? 'PH' : 'Cuti'),
                'employee_nik' => $employee->nik,
                'employee_name' => $employee->nama_karyawan,
                'approval_type' => $approvedAbsence['approval_type'],
                'approval_id' => $approvedAbsence['approval_id'],
                'approval_label' => $approvedAbsence['label'],
                'message' => "{$approvedAbsence['label']} sudah disetujui atasan. Karena karyawan tercatat scan hari ini, pembatalan pengajuan dilakukan oleh HRD agar jatah kembali dan data tidak bentrok.",
            ];
        }

        if ($this->isNonWorkdaySchedule($schedule)) {
            $actions[] = [
                'key' => 'edit_schedule_'.$employee->nik.'_'.($schedule?->schedule_date?->toDateString() ?? 'today'),
                'type' => 'edit_schedule',
                'label' => 'Ubah Jadwal Hari Ini',
                'employee_nik' => $employee->nik,
                'employee_name' => $employee->nama_karyawan,
                'date' => $schedule->schedule_date?->toDateString(),
                'schedule_code' => $schedule->schedule_code,
                'schedule_label' => $schedule->category?->name,
                'message' => 'Karyawan tercatat scan pada jadwal non-kerja. Ubah jadwal hari ini ke kode kerja yang sesuai, misalnya P0, P1, M0, atau jadwal lainnya.',
            ];
        }

        return $actions;
    }

    private function subordinateApprovedAbsencesToday(Collection $subordinateNiks, Carbon $date): Collection
    {
        if ($subordinateNiks->isEmpty()) {
            return collect();
        }

        $users = User::query()
            ->whereIn('username', $subordinateNiks)
            ->get(['id', 'username'])
            ->keyBy('id');
        $userIds = $users->keys();
        $absences = collect();

        LeaveRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereNotNull('manager_approved_at')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get(['id', 'user_id', 'leave_type'])
            ->each(function (LeaveRequest $request) use ($users, $absences): void {
                $nik = $users->get($request->user_id)?->username;
                if ($nik) {
                    $absences->put($nik, [
                        'status' => 'leave',
                        'approval_type' => 'leave',
                        'approval_id' => $request->id,
                        'label' => LeaveRequest::LEAVE_TYPES[$request->leave_type] ?? 'Cuti',
                    ]);
                }
            });

        PublicHolidayRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereNotNull('manager_approved_at')
            ->whereDate('claim_date', $date)
            ->get(['id', 'user_id'])
            ->each(function (PublicHolidayRequest $request) use ($users, $absences): void {
                $nik = $users->get($request->user_id)?->username;
                if ($nik) {
                    $absences->put($nik, [
                        'status' => 'ph',
                        'approval_type' => 'ph',
                        'approval_id' => $request->id,
                        'label' => 'PH',
                    ]);
                }
            });

        EmployeePermission::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'approved')
            ->whereNotNull('manager_approved_at')
            ->whereDate('date', $date)
            ->get(['id', 'user_id', 'type'])
            ->each(function (EmployeePermission $request) use ($users, $absences): void {
                $nik = $users->get($request->user_id)?->username;
                if ($nik) {
                    $isSick = $request->type === 'sakit';
                    $absences->put($nik, [
                        'status' => $isSick ? 'sick' : 'permission',
                        'approval_type' => 'permission',
                        'approval_id' => $request->id,
                        'label' => $isSick ? 'Sakit' : 'Izin',
                    ]);
                }
            });

        return $absences;
    }

    private function isOffSchedule(?EmployeeDailySchedule $schedule): bool
    {
        if (! $schedule) {
            return false;
        }

        $code = strtoupper((string) ($schedule->schedule_code ?: $schedule->category?->code));

        return $code === 'O' || $schedule->category?->type === 'off';
    }

    private function isNonWorkdaySchedule(?EmployeeDailySchedule $schedule): bool
    {
        return $schedule !== null && $schedule->category !== null && ! $schedule->category->is_workday;
    }

    private function dailyScanSummary(Collection $logs): array
    {
        $hasStatusCodes = $logs->contains(
            fn (FingerspotAttendanceLog $log) => in_array((string) $log->status_scan, ['0', '1'], true)
        );

        if ($hasStatusCodes) {
            $scanIn = $logs->first(
                fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '0'
            );
            $scanOut = $logs->reverse()->first(
                fn (FingerspotAttendanceLog $log) => (string) $log->status_scan === '1'
            );
        } else {
            $scanIn = $logs->first();
            $scanOut = $logs->count() > 1 ? $logs->last() : null;
        }

        return [
            'scan_in' => $scanIn?->scan_date?->format('H:i:s'),
            'scan_out' => $scanOut?->scan_date?->format('H:i:s'),
        ];
    }

    private function weeklyAttendance(Karyawan $employee, Carbon $today): array
    {
        $start = $today->copy()->startOfWeek(Carbon::MONDAY);
        $end = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $logsByDate = $employee->pin
            ? FingerspotAttendanceLog::query()
                ->where('pin', $employee->pin)
                ->whereBetween('scan_date', [$start, $today->copy()->endOfDay()])
                ->orderBy('scan_date')
                ->get(['scan_date', 'status_scan'])
                ->groupBy(fn (FingerspotAttendanceLog $log) => $log->scan_date->toDateString())
            : collect();

        $days = collect(CarbonPeriod::create($start, $end))->map(function (Carbon $date) use ($today, $logsByDate): array {
            $scans = $this->dailyScanSummary($logsByDate->get($date->toDateString(), collect()));
            $durationMinutes = $scans['scan_in'] && $scans['scan_out']
                ? (int) max(0, Carbon::createFromFormat('H:i:s', $scans['scan_in'])->diffInMinutes(
                    Carbon::createFromFormat('H:i:s', $scans['scan_out']),
                    false
                ))
                : 0;
            $status = $date->gt($today)
                ? 'future'
                : match (true) {
                    $scans['scan_in'] && $scans['scan_out'] => 'checked_out',
                    $scans['scan_in'] && $date->isSameDay($today) => 'working',
                    $scans['scan_in'] => 'missing_out',
                    $scans['scan_out'] => 'missing_in',
                    default => 'absent',
                };

            return [
                'date' => $date->toDateString(),
                'scan_in' => $scans['scan_in'],
                'scan_out' => $scans['scan_out'],
                'status' => $status,
                'duration_minutes' => $durationMinutes,
                'duration' => $this->durationLabel($durationMinutes),
            ];
        });

        $totalMinutes = $days->sum('duration_minutes');

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_duration_minutes' => $totalMinutes,
            'total_duration' => $this->durationLabel($totalMinutes),
            'days' => $days->values(),
        ];
    }

    private function durationLabel(int $minutes): string
    {
        return intdiv($minutes, 60).' jam '.($minutes % 60).' menit';
    }

    private function subordinateUserIds(User $user)
    {
        return User::query()->whereIn('username', $this->subordinateNiks($user))->pluck('id');
    }

    private function hasDirectSubordinates(User $user): bool
    {
        return $this->subordinateNiks($user)->isNotEmpty();
    }

    private function pendingApprovalsFor(User $user): Collection
    {
        $subordinateUserIds = $this->subordinateUserIds($user);

        return LeaveRequest::query()
            ->with('user.karyawan')
            ->whereIn('user_id', $subordinateUserIds)
            ->whereNull('manager_approved_at')
            ->where('status', 'pending')
            ->latest()
            ->get()
            ->map(fn (LeaveRequest $item) => $this->serializeApproval('leave', $item))
            ->concat(
                PublicHolidayRequest::query()
                    ->with(['user.karyawan', 'holiday'])
                    ->whereIn('user_id', $subordinateUserIds)
                    ->whereNull('manager_approved_at')
                    ->where('status', 'pending')
                    ->latest()
                    ->get()
                    ->map(fn (PublicHolidayRequest $item) => $this->serializeApproval('ph', $item))
            )
            ->concat(
                EmployeePermission::query()
                    ->with('user.karyawan')
                    ->whereIn('user_id', $subordinateUserIds)
                    ->whereNull('manager_approved_at')
                    ->where('status', 'pending')
                    ->latest()
                    ->get()
                    ->map(fn (EmployeePermission $item) => $this->serializeApproval('permission', $item))
            )
            ->sortByDesc('created_at')
            ->values();
    }

    private function requiresDirectHrApproval(User $user): bool
    {
        $employee = $this->employeeFor($user);
        $positionTitle = strtolower(trim((string) ($employee->posisi_title ?: $employee->jabatan ?: $employee->posisi)));

        return in_array($positionTitle, ['manager', 'gm', 'general manager'], true);
    }

    private function initialApprovalFields(bool $directToHr): array
    {
        return $directToHr
            ? [
                'manager_approved_at' => now(),
                'manager_approved_by' => null,
                'approval_token' => null,
                'approval_token_expires_at' => null,
            ]
            : [
                'approval_token' => (string) Str::uuid(),
                'approval_token_expires_at' => now()->addHours(24),
            ];
    }

    private function notifyInitialApprover(object $request, string $type, bool $directToHr): void
    {
        if ($directToHr) {
            $this->approvalNotification->notifyHrGroups($request, $type);

            return;
        }

        $this->approvalNotification->notifyManager($request, $type);
    }

    private function isDirectSubordinateRequest(User $manager, object $approvalRequest): bool
    {
        return $approvalRequest->user
            && $this->subordinateNiks($manager)->contains($approvalRequest->user->username);
    }

    private function serializePermission(EmployeePermission $permission): array
    {
        return [
            ...$permission->toArray(),
            'document_url' => $permission->document ? asset('storage/'.$permission->document) : null,
        ];
    }

    private function serializeApproval(string $type, object $item): array
    {
        return [
            'id' => $item->id,
            'type' => $type,
            'employee_nik' => $item->user->username,
            'employee_name' => $item->user->karyawan->nama_karyawan ?? $item->user->name,
            'label' => match ($type) {
                'leave' => LeaveRequest::LEAVE_TYPES[$item->leave_type] ?? $item->leave_type,
                'ph' => $item->holiday->name ?? 'Public Holiday',
                default => $item->type === 'sakit' ? 'Sakit' : 'Izin',
            },
            'start_date' => match ($type) {
                'leave' => $item->start_date,
                'ph' => $item->claim_date?->toDateString(),
                default => $item->date?->toDateString(),
            },
            'end_date' => $type === 'leave' ? $item->end_date : null,
            'reason' => $item->reason ?? null,
            'status' => $item->status,
            'created_at' => $item->created_at,
        ];
    }

    private function ensureUserForEmployee(string $nik): User
    {
        $existing = User::query()->where('username', $nik)->first();

        if ($existing) {
            return $existing;
        }

        $employee = Karyawan::query()->where('nik', $nik)->firstOrFail();
        $email = $employee->email ?: $employee->nik.'@hris.local';

        if (User::query()->where('email', $email)->exists()) {
            $email = $employee->nik.'@hris.local';
        }

        return User::create([
            'username' => $employee->nik,
            'name' => $employee->nama_karyawan,
            'email' => $email,
            'password' => Hash::make('12345678'),
            'level' => 3,
            'must_change_password' => true,
        ]);
    }
}
