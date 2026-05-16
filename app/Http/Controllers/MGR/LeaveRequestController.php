<?php

namespace App\Http\Controllers\MGR;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\Karyawan;
use App\Notifications\LeaveStatusNotification;
use Illuminate\Support\Facades\Auth;

class LeaveRequestController extends Controller
{
    public function index()
    {
        $leaveRequests = collect();

        return view('mgr.leave.approval', compact('leaveRequests'));
    }

    private function isMyIndirectSubordinate($leave)
    {
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->first();

        $bawahan = Karyawan::where('atasan_tidak_langsung', $me->nama_karyawan)
            ->pluck('nik')
            ->toArray();

        $userNik = $leave->user->username;

        return in_array($userNik, $bawahan) && $leave->manager_approved_at !== null;
    }

    public function approve($id)
    {
        return back()->with('error', 'Approval atasan tidak langsung sudah tidak digunakan untuk cuti.');
    }

    public function reject($id)
    {
        return back()->with('error', 'Approval atasan tidak langsung sudah tidak digunakan untuk cuti.');
    }
}
