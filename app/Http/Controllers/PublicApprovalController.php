<?php

namespace App\Http\Controllers;

use App\Http\Services\ApprovalNotificationService;
use App\Models\EmployeePermission;
use App\Models\FingerspotAttendanceLog;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use App\Notifications\RequestStatusNotification;
use Illuminate\Http\Request;

class PublicApprovalController extends Controller
{
    public function __construct(private ApprovalNotificationService $approvalNotificationService) {}

    /*
    |--------------------------------------------------------------------------
    | SHOW APPROVAL PAGE
    |--------------------------------------------------------------------------
    */
    private const PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM = '2026-05-27';

    public function show(string $token)
    {
        $request = $this->findRequestByToken($token);

        if (! $request) {
            return view('approval.invalid');
        }

        if ($request->status !== 'pending') {
            return view('approval.already-processed');
        }

        if (
            ! $request->approval_token_expires_at ||
            now()->greaterThan($request->approval_token_expires_at)
        ) {
            return view('approval.expired');
        }

        return view('approval.show', [
            'request' => $request,
            'type' => $this->detectType($request),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */
    public function approve(string $token)
    {
        $request = $this->findRequestByToken($token);

        if (! $request) {
            return view('approval.invalid');
        }

        if ($request->status !== 'pending') {
            return view('approval.already-processed');
        }

        if (
            ! $request->approval_token_expires_at ||
            now()->greaterThan($request->approval_token_expires_at)
        ) {
            return view('approval.expired');
        }

        if ($request instanceof PublicHolidayRequest && ! $this->hasWorkedOnPublicHoliday($request)) {
            return view('approval.ph-attendance-required');
        }

        $request->update([
            'manager_approved_at' => now(),
            'manager_approved_by' => null, // karena via token
            'status' => 'approved',
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ]);

        // 🔔 Notify Staff
        $this->approvalNotificationService->notifyIndirectManagerOfDirectManagerDecision(
            $request,
            $this->notificationType($request),
            'approved'
        );
        $this->approvalNotificationService->notifyHrGroups(
            $request,
            $this->notificationType($request)
        );

        $this->notifyUser($request, 'approved');

        return view('approval.approved-success');
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */
    public function reject(string $token)
    {
        $request = $this->findRequestByToken($token);

        if (! $request) {
            return view('approval.invalid');
        }

        if ($request->status !== 'pending') {
            return view('approval.already-processed');
        }

        if (
            ! $request->approval_token_expires_at ||
            now()->greaterThan($request->approval_token_expires_at)
        ) {
            return view('approval.expired');
        }

        $request->update([
            'manager_approved_at' => now(),
            'manager_approved_by' => null, // karena via token
            'status' => 'rejected',
            'approval_token' => null,
            'approval_token_expires_at' => null,
        ]);

        // 🔔 Notify Staff
        $this->approvalNotificationService->notifyIndirectManagerOfDirectManagerDecision(
            $request,
            $this->notificationType($request),
            'rejected'
        );

        $this->notifyUser($request, 'rejected');

        return view('approval.rejected-success');
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: FIND REQUEST
    |--------------------------------------------------------------------------
    */
    private function findRequestByToken($token)
    {
        return LeaveRequest::with('user')
            ->where('approval_token', $token)
            ->first()
            ?? PublicHolidayRequest::with('user', 'holiday')
                ->where('approval_token', $token)
                ->first()
            ?? EmployeePermission::with('user')
                ->where('approval_token', $token)
                ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: DETECT TYPE
    |--------------------------------------------------------------------------
    */
    private function detectType($request)
    {
        if ($request instanceof LeaveRequest) {
            return 'leave';
        }

        if ($request instanceof PublicHolidayRequest) {
            return 'ph';
        }

        if ($request instanceof EmployeePermission) {
            return 'permission';
        }

        return 'unknown';
    }

    private function notificationType($request): string
    {
        return match (true) {
            $request instanceof LeaveRequest => 'CUTI',
            $request instanceof PublicHolidayRequest => 'PH',
            $request instanceof EmployeePermission => 'IZIN',
            default => 'UNKNOWN',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER: NOTIFY USER
    |--------------------------------------------------------------------------
    */
    private function notifyUser($request, $status)
    {
        if ($request instanceof LeaveRequest) {
            $request->user->notify(
                new LeaveStatusNotification($request, $status)
            );
        }

        if ($request instanceof PublicHolidayRequest) {
            $request->user->notify(
                new PublicHolidayStatusNotification($request, $status)
            );
        }

        if ($request instanceof EmployeePermission) {
            $request->user->notify(
                new RequestStatusNotification($request, 'IZIN', $status)
            );
        }

    }

    private function hasWorkedOnPublicHoliday(PublicHolidayRequest $request): bool
    {
        $holiday = $request->holiday;
        $employee = $request->user?->karyawan;

        return $holiday
            && $holiday->is_active
            && ($holiday->holiday_date->lt(self::PUBLIC_HOLIDAY_ATTENDANCE_REQUIRED_FROM)
                || ($employee?->pin
                    && FingerspotAttendanceLog::query()
                        ->where('pin', $employee->pin)
                        ->whereDate('scan_date', $holiday->holiday_date)
                        ->exists()));
    }
}
