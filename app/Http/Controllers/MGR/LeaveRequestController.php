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
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->firstOrFail();

        // Cari bawahan tidak langsung: karyawan yang atasan_tidak_langsung nya adalah saya
        $bawahanTidakLangsung = Karyawan::where('atasan_tidak_langsung', $me->nama_karyawan)
            ->pluck('nik');

        if ($bawahanTidakLangsung->isEmpty()) {
            abort(403, 'Anda tidak memiliki bawahan tidak langsung.');
        }

        // Fetch Leave Requests yang sudah disetujui oleh atasan langsung
        $leaveRequests = LeaveRequest::whereIn('user_id', function ($q) use ($bawahanTidakLangsung) {
            $q->select('id')
                ->from('users')
                ->whereIn('username', $bawahanTidakLangsung);
        })
            ->whereNotNull('manager_approved_at')
            ->whereNull('second_manager_approved_at')
            ->latest()
            ->get();

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
        $leave = LeaveRequest::with('user')->findOrFail($id);

        if (! $this->isMyIndirectSubordinate($leave)) {
            abort(403, 'Anda tidak berhak menyetujui cuti ini.');
        }

        if ($leave->second_manager_approved_at !== null) {
            return back()->with('error', 'Cuti sudah diproses oleh atasan tidak langsung.');
        }

        $leave->update([
            'second_manager_approved_at' => now(),
            'second_manager_approved_by' => Auth::id(),
        ]);

        return back()->with('success', 'Cuti berhasil disetujui oleh atasan tidak langsung');
    }

    public function reject($id)
    {
        $leave = LeaveRequest::with('user')->findOrFail($id);

        if (! $this->isMyIndirectSubordinate($leave)) {
            abort(403, 'Anda tidak berhak menyetujui cuti ini.');
        }

        if ($leave->second_manager_approved_at !== null) {
            return back()->with('error', 'Cuti sudah diproses.');
        }

        $leave->update([
            'status' => 'rejected',
            'second_manager_approved_by' => Auth::id(),
        ]);

        // Kirim notifikasi ke staff
        $leave->user->notify(
            new LeaveStatusNotification($leave, 'rejected')
        );

        return back()->with('success', 'Cuti berhasil ditolak oleh atasan tidak langsung');
    }
}
