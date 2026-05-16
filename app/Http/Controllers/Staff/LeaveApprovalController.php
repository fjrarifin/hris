<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\PublicHolidayRequest;
use App\Models\Karyawan;
use Illuminate\Support\Facades\Auth;
use App\Notifications\LeaveStatusNotification;
use App\Notifications\PublicHolidayStatusNotification;
use App\Http\Services\ApprovalNotificationService;
use App\Models\User;

class LeaveApprovalController extends Controller
{

    protected $approvalNotification;

    public function __construct(ApprovalNotificationService $approvalNotification)
    {
        $this->approvalNotification = $approvalNotification;
    }

    public function index()
    {
        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->firstOrFail();

        // bawahan langsung
        $directSubordinates = Karyawan::where('nama_atasan_langsung', $me->nama_karyawan)
            ->pluck('nik');

        $leaveRequests = LeaveRequest::where(function ($q) use ($directSubordinates) {
            $q->whereIn('user_id', function ($q2) use ($directSubordinates) {
                $q2->select('id')
                    ->from('users')
                    ->whereIn('username', $directSubordinates);
            })
                ->whereNull('manager_approved_at')
                ->where('status', 'pending');
        })
            ->latest()
            ->get();

        $requests = $leaveRequests;

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

        $nikLogin = Auth::user()->username;

        $me = Karyawan::where('nik', $nikLogin)->first();

        $staff = Karyawan::where('nik', $leave->user->username)->first();

        if ($staff->nama_atasan_langsung == $me->nama_karyawan && !$leave->manager_approved_at) {

            $leave->update([
                'manager_approved_at' => now(),
                'manager_approved_by' => Auth::id(),
                'status' => 'approved',
            ]);

            $this->approvalNotification->notifyIndirectManagerOfDirectManagerDecision($leave, 'CUTI', 'approved');
            $leave->user->notify(
                new LeaveStatusNotification($leave, 'approved')
            );

            return back()->with('success', 'Cuti disetujui atasan langsung');
        }

        abort(403, 'Anda tidak berhak menyetujui ini.');
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
        $this->approvalNotification->notifyIndirectManagerOfDirectManagerDecision($leave, 'CUTI', 'rejected');

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

        $this->approvalNotification->notifyIndirectManagerOfDirectManagerDecision($phRequest, 'PH', 'approved');

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
        $this->approvalNotification->notifyIndirectManagerOfDirectManagerDecision($phRequest, 'PH', 'rejected');

        $phRequest->user->notify(
            new PublicHolidayStatusNotification($phRequest, 'rejected')
        );

        return back()->with('success', 'PH berhasil ditolak');
    }
}
