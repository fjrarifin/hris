<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LeaveRequest;
use App\Notifications\LeaveStatusNotification;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{
    public function index()
    {
        $requests = LeaveRequest::with('user')
            ->whereNotNull('manager_approved_at')
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->latest()
            ->get();

        return view('hr.leave.approval', compact('requests'));
    }


    public function approve($id)
    {
        $leave = LeaveRequest::with('user')->findOrFail($id);

        $leave->update([
            'hr_approved_at' => now(),
            'hr_approved_by' => Auth::id(),
            'status' => 'approved',
        ]);

        // notif ke staff
        $leave->user->notify(
            new LeaveStatusNotification($leave, 'approved')
        );

        return back()->with('success', 'Cuti disetujui HR');
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|max:255'
        ]);

        $leave = LeaveRequest::with('user')->findOrFail($id);

        $leave->update([
            'status' => 'rejected',
            'reject_reason' => $request->reason,
            'hr_approved_by' => Auth::id(),
        ]);

        $leave->user->notify(
            new LeaveStatusNotification($leave, 'rejected')
        );

        return back()->with('success', 'Cuti ditolak HR');
    }


    public function cancel(Request $request, $id)
    {
        $request->validate([
            'reject_reason' => 'required|string|max:255'
        ]);

        $leave = LeaveRequest::with('user')->findOrFail($id);

        $leave->update([
            'status' => 'cancelled',
            'reject_reason' => $request->reject_reason,
            'hr_approved_by' => Auth::id(),
        ]);

        $leave->user->notify(
            new LeaveStatusNotification($leave, 'cancelled')
        );

        return back()->with('success', 'Cuti dibatalkan oleh HR');
    }
}
