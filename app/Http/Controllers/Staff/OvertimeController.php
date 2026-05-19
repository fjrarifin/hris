<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Services\ApprovalNotificationService;
use App\Models\Karyawan;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Notifications\ApprovalRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class OvertimeController extends Controller
{
    public function __construct(private ApprovalNotificationService $approvalNotificationService)
    {
    }

    public function index()
    {
        $user = Auth::user();
        $subordinateNiks = $this->subordinateNiks($user);

        $subordinates = Karyawan::whereIn('nik', $subordinateNiks)
            ->orderBy('nama_karyawan')
            ->get();

        $requests = OvertimeRequest::with(['user.karyawan', 'requestedBy'])
            ->where('requested_by_user_id', $user->id)
            ->latest()
            ->get();

        return view('staff.overtime.index', compact('requests', 'subordinates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_niks' => ['required', 'array', 'min:1'],
            'employee_niks.*' => ['string', 'exists:m_karyawan,nik'],
            'date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required'],
            'end_time' => ['required', 'after:start_time'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $manager = Auth::user();
        $subordinateNiks = $this->subordinateNiks($manager);

        $employeeNiks = collect($data['employee_niks'])
            ->map(fn ($nik) => (string) $nik)
            ->unique()
            ->values();

        $invalid = $employeeNiks->diff($subordinateNiks)->isNotEmpty();

        if ($invalid) {
            return back()
                ->withErrors(['employee_niks' => 'Karyawan yang dipilih harus bawahan langsung Anda.'])
                ->withInput();
        }

        $employeeUserIds = $employeeNiks
            ->map(fn ($nik) => $this->ensureUserForKaryawan($nik)->id)
            ->values();

        $overlap = OvertimeRequest::whereIn('user_id', $employeeUserIds)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('date', $data['date'])
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlap) {
            return back()
                ->withErrors(['start_time' => 'Salah satu karyawan sudah punya pengajuan lembur pada rentang jam tersebut.'])
                ->withInput();
        }

        $created = collect();

        foreach ($employeeUserIds as $employeeUserId) {
            $created->push(OvertimeRequest::create([
                'user_id' => $employeeUserId,
                'requested_by_user_id' => $manager->id,
                'date' => $data['date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'reason' => $data['reason'],
                'status' => 'pending',
            ]));
        }

        $this->notifyHrAdmins($created->first());
        $created->each(fn (OvertimeRequest $overtime) => $this->approvalNotificationService->notifyHrGroups($overtime, 'LEMBUR'));

        return back()->with('success', 'Pengajuan lembur berhasil dikirim ke HR.');
    }

    public function destroy($id)
    {
        OvertimeRequest::where('id', $id)
            ->where('requested_by_user_id', Auth::id())
            ->where('status', 'pending')
            ->delete();

        return back()->with('success', 'Pengajuan lembur berhasil dihapus');
    }

    private function subordinateNiks(User $user)
    {
        $me = Karyawan::where('nik', $user->username)->first();

        if (! $me) {
            return collect();
        }

        return Karyawan::where('nama_atasan_langsung', $me->nama_karyawan)
            ->pluck('nik');
    }

    private function notifyHrAdmins(?OvertimeRequest $overtime): void
    {
        if (! $overtime) {
            return;
        }

        User::whereIn('username', ['hrd0001', 'hrd0002'])
            ->get()
            ->each(fn (User $hr) => $hr->notify(
                new ApprovalRequestNotification($overtime->loadMissing('user'), 'LEMBUR')
            ));
    }

    private function ensureUserForKaryawan(string $nik): User
    {
        $user = User::where('username', $nik)->first();

        if ($user) {
            return $user;
        }

        $karyawan = Karyawan::where('nik', $nik)->firstOrFail();
        $email = $karyawan->email ?: $karyawan->nik . '@hris.local';

        if (User::where('email', $email)->exists()) {
            $email = $karyawan->nik . '@hris.local';
        }

        return User::create([
            'username' => $karyawan->nik,
            'name' => $karyawan->nama_karyawan,
            'email' => $email,
            'password' => Hash::make('password'),
            'level' => 3,
            'must_change_password' => true,
        ]);
    }
}
