<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;

class DashboardController extends Controller
{
    public function index()
    {
        // 🔴 Pending HR (sudah disetujui manager, belum HR)
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

        // 🔴 Pending Count
        $leavePendingCount = LeaveRequest::whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        $phPendingCount = PublicHolidayRequest::whereNotNull('manager_approved_at')
            ->whereNull('hr_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->count();

        // 🟢 Approved Final (sudah HR approve)
        $leaveApprovedCount = LeaveRequest::whereNotNull('hr_approved_at')
            ->where('status', 'approved')
            ->count();

        $phApprovedCount = PublicHolidayRequest::whereNotNull('hr_approved_at')
            ->where('status', 'approved')
            ->count();

        return view('hr.dashboard', compact(
            'pendingLeave',
            'pendingPH',
            'leavePendingCount',
            'phPendingCount',
            'leaveApprovedCount',
            'phApprovedCount'
        ));
    }
}
