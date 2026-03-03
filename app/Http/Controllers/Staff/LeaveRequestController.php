<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Http\Services\ApprovalNotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendLeaveRequestWhatsApp;
use App\Models\PublicHolidayRequest;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Karyawan;

class LeaveRequestController extends Controller
{

    protected ApprovalNotificationService $approvalNotificationService;

    public function __construct(ApprovalNotificationService $approvalNotificationService)
    {
        $this->approvalNotificationService = $approvalNotificationService;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        }

        return $phone;
    }
    public function index()
    {
        $user = Auth::user();

        $accruals = $user->accruals()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();

        $total = $accruals->count();
        $used = $accruals->where('is_used', true)->count();
        $available = $accruals->where('is_used', false)
            ->where('expired_at', '>=', now())
            ->count();

        $requests = LeaveRequest::where('user_id', $user->id)
            ->latest()
            ->get();

        return view('staff.leave.index', compact(
            'requests',
            'accruals',
            'total',
            'used',
            'available'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'leave_type' => 'required|string',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'nullable|string|max:255',
        ]);

        $start = \Carbon\Carbon::parse($data['start_date']);
        $end   = \Carbon\Carbon::parse($data['end_date']);

        $durasi = $start->diffInDays($end) + 1;

        if ($durasi > 5) {
            return back()
                ->withErrors([
                    'end_date' => 'Maksimal pengajuan cuti adalah 5 hari.'
                ])
                ->withInput();
        }

        $user = Auth::user();
        $data['user_id'] = $user->id;

        // ❌ Bentrok dengan cuti lain
        $exists = LeaveRequest::where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->where(function ($query) use ($start, $end) {
                $query->whereDate('start_date', '<=', $end)
                    ->whereDate('end_date', '>=', $start);
            })
            ->exists();

        if ($exists) {
            return back()
                ->withErrors([
                    'start_date' => 'Tanggal cuti bertabrakan dengan pengajuan sebelumnya.'
                ])
                ->withInput();
        }

        // ❌ Bentrok dengan PH
        $phBentrok = PublicHolidayRequest::where('user_id', $user->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('claim_date', '>=', $start)
            ->whereDate('claim_date', '<=', $end)
            ->exists();

        if ($phBentrok) {
            return back()
                ->withErrors([
                    'start_date' => 'Tanggal cuti bentrok dengan Public Holiday yang sudah diajukan.'
                ])
                ->withInput();
        }

        $leave = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => $data['leave_type'],
            'start_date' => $start,
            'end_date' => $end,
            'reason' => $data['reason'],
            'status' => 'pending',
            'approval_token' => (string) Str::uuid(),
            'approval_token_expires_at' => now()->addHours(24),
        ]);

        // 🔥 Kirim notif & WA via service
        $this->approvalNotificationService
            ->notifyManager($leave, 'CUTI');

        return back()->with('success', 'Pengajuan cuti berhasil dikirim.');
    }

    public function destroy($id)
    {
        $leave = LeaveRequest::where('id', $id)
            ->where('user_id', Auth::user()->id)
            ->where('status', 'pending')
            ->firstOrFail();

        $leave->delete();

        return redirect()->back()->with('success', 'Pengajuan cuti berhasil dihapus');
    }
}
