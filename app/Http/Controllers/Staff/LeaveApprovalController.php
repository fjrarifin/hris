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

        // bawahan tidak langsung
        $indirectSubordinates = Karyawan::where('atasan_tidak_langsung', $me->nama_karyawan)
            ->pluck('nik');

        $leaveRequests = LeaveRequest::where(function ($q) use ($directSubordinates, $indirectSubordinates) {

            // Level 1 approval
            $q->whereIn('user_id', function ($q2) use ($directSubordinates) {
                $q2->select('id')
                    ->from('users')
                    ->whereIn('username', $directSubordinates);
            })
                ->whereNull('manager_approved_at');
        })->orWhere(function ($q) use ($indirectSubordinates) {

            // Level 2 approval
            $q->whereIn('user_id', function ($q2) use ($indirectSubordinates) {
                $q2->select('id')
                    ->from('users')
                    ->whereIn('username', $indirectSubordinates);
            })
                ->whereNotNull('manager_approved_at')
                ->whereNull('second_manager_approved_at');
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
            ->orWhere('atasan_tidak_langsung', $me->nama_karyawan) // Cek juga atasan tidak langsung
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

        // LEVEL 1 APPROVAL
        if ($staff->nama_atasan_langsung == $me->nama_karyawan && !$leave->manager_approved_at) {

            $leave->update([
                'manager_approved_at' => now(),
                'manager_approved_by' => Auth::id(),
            ]);

            $this->approvalNotification->notifySecondManager($leave);

            return back()->with('success', 'Cuti disetujui atasan langsung');
        }

        // LEVEL 2 APPROVAL
        if ($staff->atasan_tidak_langsung == $me->nama_karyawan && $leave->manager_approved_at && !$leave->second_manager_approved_at) {

            $leave->update([
                'second_manager_approved_at' => now(),
                'second_manager_approved_by' => Auth::id(),
            ]);

            return back()->with('success', 'Cuti disetujui atasan tidak langsung');
        }

        abort(403, 'Anda tidak berhak approve ini.');
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
