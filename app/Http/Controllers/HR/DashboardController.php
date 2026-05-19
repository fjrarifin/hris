<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeePermission;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PublicHolidayRequest;

class DashboardController extends Controller
{
    public function index()
    {
        $pendingLeave = LeaveRequest::with('user')
            ->whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->take(5)
            ->get();

        $pendingPH = PublicHolidayRequest::with('user', 'holiday')
            ->whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->take(5)
            ->get();

        $pendingPermission = EmployeePermission::with('user')
            ->whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->take(5)
            ->get();

        $pendingOvertime = OvertimeRequest::with(['user', 'requestedBy'])
            ->whereNotNull('requested_by_user_id')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->take(5)
            ->get();

        $pendingApprovals = $pendingLeave
            ->map(fn ($request) => (object) ['type' => 'leave', 'request' => $request])
            ->concat($pendingPH->map(fn ($request) => (object) ['type' => 'ph', 'request' => $request]))
            ->concat($pendingPermission->map(fn ($request) => (object) ['type' => 'permission', 'request' => $request]))
            ->concat($pendingOvertime->map(fn ($request) => (object) ['type' => 'overtime', 'request' => $request]))
            ->sortByDesc(fn ($item) => $item->request->created_at)
            ->take(10)
            ->values();

        $leavePendingCount = LeaveRequest::whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $phPendingCount = PublicHolidayRequest::whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $permissionPendingCount = EmployeePermission::whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $overtimePendingCount = OvertimeRequest::whereNotNull('requested_by_user_id')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $leaveApprovedCount = LeaveRequest::whereNotNull('hr_approved_at')
            ->where('status', 'approved')
            ->count();

        $phApprovedCount = PublicHolidayRequest::whereNotNull('hr_approved_at')
            ->where('status', 'approved')
            ->count();

        return view('hr.dashboard', compact(
            'pendingApprovals',
            'pendingLeave',
            'pendingPH',
            'pendingPermission',
            'pendingOvertime',
            'leavePendingCount',
            'phPendingCount',
            'permissionPendingCount',
            'overtimePendingCount',
            'leaveApprovedCount',
            'phApprovedCount'
        ));
    }
}
