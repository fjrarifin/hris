<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PublicHolidayRequest;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Auth;
use App\Notifications\LeaveStatusNotification;
use App\Models\User;

class LeaveApprovalController extends Controller
{
    public function index()
    {
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->firstOrFail();

        $bawahanNik = Karyawan::where('nama_atasan_langsung', $me->nama_karyawan)
            ->pluck('nik');

        $requests = PublicHolidayRequest::with(['user', 'holiday'])
            ->whereIn('user_id', function ($q) use ($bawahanNik) {
                $q->select('id')
                    ->from('users')
                    ->whereIn('username', $bawahanNik);
            })
            ->where('status', 'pending')
            ->whereNull('manager_approved_at')
            ->latest()
            ->get();

        return view('staff.public-holiday.approval', compact('requests'));
    }


    private function isMySubordinate($leave)
    {
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->first();

        $bawahan = Karyawan::where('nama_atasan_langsung', $me->nama_karyawan)
            ->pluck('nik')
            ->toArray();

        $userNik = $leave->user->username;

        return in_array($userNik, $bawahan);
    }


    public function approve($id)
    {
        $request = PublicHolidayRequest::with('user')->findOrFail($id);

        if (!$this->isMySubordinate($request)) {
            abort(403);
        }

        $request->update([
            'manager_approved_at' => now(),
            'manager_approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'PH disetujui atasan');
    }


    public function reject(Request $request, $id)
    {
        $request->validate([
            'reject_reason' => 'required|string|max:255'
        ]);

        $ph = PublicHolidayRequest::with('user')->findOrFail($id);

        if (!$this->isMySubordinate($request)) {
            abort(403);
        }

        $ph->update([
            'status' => 'rejected',
            'reject_reason' => $request->reject_reason,
            'manager_approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'PH ditolak atasan');
    }
}
