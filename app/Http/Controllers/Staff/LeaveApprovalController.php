<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Auth;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use App\Models\User;

class LeaveApprovalController extends Controller
{
    public function index()
    {
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->firstOrFail();

        // Cari semua bawahan berdasarkan atasan langsung
        $bawahanNik = Karyawan::where('nama_atasan_langsung', $me->nama_karyawan)
            ->pluck('nik');

        if ($bawahanNik->isEmpty()) {
            abort(403, 'Anda tidak memiliki bawahan.');
        }

        // Fetch Leave Requests
        $leaveRequests = LeaveRequest::whereIn('user_id', function ($q) use ($bawahanNik) {
            $q->select('id')
                ->from('users')
                ->whereIn('username', $bawahanNik);
        })
            ->latest()
            ->get();

        // Fetch Public Holiday Requests
        $phRequests = PublicHolidayRequest::whereIn('user_id', function ($q) use ($bawahanNik) {
            $q->select('id')
                ->from('users')
                ->whereIn('username', $bawahanNik);
        })
            ->latest()
            ->get();

        // Merge and sort by created_at
        $requests = $leaveRequests->concat($phRequests)->sortByDesc('created_at');

        return view('staff.leave.approval', compact('requests'));
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
        $leave = LeaveRequest::with('user')->findOrFail($id);

        if (! $this->isMySubordinate($leave)) {
            abort(403, 'Anda tidak berhak menyetujui cuti ini.');
        }

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Cuti sudah diproses.');
        }

        $leave->update([
            'status' => 'approved',
            'manager_approved_at' => now(),
            'manager_approved_by' => Auth::id(),
        ]);


        // 🔔 Kirim notifikasi ke staff
        $leave->user->notify(
            new LeaveStatusNotification($leave, 'approved')
        );

        return back()->with('success', 'Cuti berhasil disetujui');
    }

    public function reject($id)
    {
        $leave = LeaveRequest::with('user')->findOrFail($id);

        if (! $this->isMySubordinate($leave)) {
            abort(403, 'Anda tidak berhak menolak cuti ini.');
        }

        if ($leave->status !== 'pending') {
            return back()->with('error', 'Cuti sudah diproses.');
        }

        $leave->update([
            'status' => 'rejected',
        ]);

        // 🔔 Kirim notifikasi ke staff
        $leave->user->notify(
            new LeaveStatusNotification($leave, 'rejected')
        );

        return back()->with('success', 'Cuti berhasil ditolak');
    }

    // Public Holiday Approval Methods
    private function isMySubordinatePH($phRequest)
    {
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->first();

        $bawahan = Karyawan::where('nama_atasan_langsung', $me->nama_karyawan)
            ->pluck('nik')
            ->toArray();

        $userNik = $phRequest->user->username;

        return in_array($userNik, $bawahan);
    }

    public function approvePH($id)
    {
        $phRequest = PublicHolidayRequest::with('user')->findOrFail($id);

        if (! $this->isMySubordinatePH($phRequest)) {
            abort(403, 'Anda tidak berhak menyetujui PH ini.');
        }

        if ($phRequest->status !== 'pending') {
            return back()->with('error', 'PH sudah diproses.');
        }

        $phRequest->update([
            'status' => 'approved',
            'manager_approved_at' => now(),
            'manager_approved_by' => Auth::id(),
        ]);

        // 🔔 Kirim notifikasi ke staff
        $phRequest->user->notify(
            new PublicHolidayStatusNotification($phRequest, 'approved')
        );

        return back()->with('success', 'PH berhasil disetujui');
    }

    public function rejectPH($id)
    {
        $phRequest = PublicHolidayRequest::with('user')->findOrFail($id);

        if (! $this->isMySubordinatePH($phRequest)) {
            abort(403, 'Anda tidak berhak menolak PH ini.');
        }

        if ($phRequest->status !== 'pending') {
            return back()->with('error', 'PH sudah diproses.');
        }

        $phRequest->update([
            'status' => 'rejected',
        ]);

        // 🔔 Kirim notifikasi ke staff
        $phRequest->user->notify(
            new PublicHolidayStatusNotification($phRequest, 'rejected')
        );

        return back()->with('success', 'PH berhasil ditolak');
    }
}
