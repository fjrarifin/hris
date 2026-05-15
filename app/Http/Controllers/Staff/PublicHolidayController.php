<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Services\ApprovalNotificationService;
use Illuminate\Http\Request;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use Illuminate\Support\Facades\Auth;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use App\Models\Karyawan;
use Illuminate\Support\Str;
use App\Models\User;
use App\Notifications\PublicHolidayStatusNotification;
use Illuminate\Support\Facades\Log;

class PublicHolidayController extends Controller
{
    protected ApprovalNotificationService $approvalNotificationService;

    public function __construct(ApprovalNotificationService $approvalNotificationService)
    {
        $this->approvalNotificationService = $approvalNotificationService;
    }

    public function index()
    {
        // Exclude PH dates that user has already submitted and approved by manager
        $approvedByManagerIds = PublicHolidayRequest::where('user_id', Auth::id())
            ->whereNotNull('manager_approved_at')
            ->where('status', 'approved')
            ->pluck('public_holiday_id');

        $holidays = PublicHoliday::where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->whereNotIn('id', $approvedByManagerIds)
            ->orderBy('holiday_date', 'desc')
            ->get();

        $requests = PublicHolidayRequest::with('holiday')
            ->where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('staff.public-holiday.index', compact('holidays', 'requests'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'public_holiday_id' => 'required|exists:public_holidays,id',
            'claim_date' => 'required|date'
        ]);

        $holiday = PublicHoliday::findOrFail($request->public_holiday_id);

        $claimDate = \Carbon\Carbon::parse($request->claim_date);
        $expiredAt = $holiday->holiday_date->copy()->addDays(90);

        // ❌ Tidak boleh sebelum hari ini
        if ($claimDate->lt(now()->startOfDay())) {
            return back()
                ->withErrors([
                    'claim_date' => 'Tanggal pengambilan tidak boleh sebelum hari ini.'
                ])
                ->withInput();
        }

        // ❌ Tidak boleh sebelum tanggal PH
        if ($claimDate->lt($holiday->holiday_date)) {
            return back()
                ->withErrors([
                    'claim_date' => 'Tanggal pengambilan tidak boleh sebelum tanggal PH.'
                ])
                ->withInput();
        }

        // ❌ Lewat masa berlaku
        if ($claimDate->gt($expiredAt)) {
            return back()
                ->withErrors([
                    'claim_date' => 'Tanggal pengambilan melewati masa berlaku PH.'
                ])
                ->withInput();
        }

        // ❌ Bentrok dengan cuti
        $cutiBentrok = LeaveRequest::where('user_id', Auth::id())
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->whereDate('start_date', '<=', $claimDate)
            ->whereDate('end_date', '>=', $claimDate)
            ->exists();

        if ($cutiBentrok) {
            return back()
                ->withErrors([
                    'claim_date' => 'Tanggal claim PH bentrok dengan pengajuan cuti.'
                ])
                ->withInput();
        }

        // ❌ Double claim PH yang sama
        $exists = PublicHolidayRequest::where('user_id', Auth::id())
            ->where('public_holiday_id', $holiday->id)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();

        if ($exists) {
            return back()
                ->withErrors([
                    'public_holiday_id' => 'Anda sudah pernah mengajukan PH ini.'
                ])
                ->withInput();
        }

        // ❌ Tidak boleh claim 2 PH di tanggal yang sama
        $doubleClaimSameDate = PublicHolidayRequest::where('user_id', Auth::id())
            ->whereDate('claim_date', $claimDate)
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->exists();

        if ($doubleClaimSameDate) {
            return back()
                ->withErrors([
                    'claim_date' => 'Anda sudah memiliki claim PH di tanggal tersebut.'
                ])
                ->withInput();
        }


        $ph = PublicHolidayRequest::create([
            'user_id' => Auth::id(),
            'public_holiday_id' => $holiday->id,
            'claim_date' => $claimDate,
            'expired_at' => $expiredAt,
            'status' => 'pending',
            'approval_token' => (string) Str::uuid(),
            'approval_token_expires_at' => now()->addHours(24),
        ]);

        // 🔥 Kirim notif & WA via service
        $this->approvalNotificationService
            ->notifyManager($ph, 'PH');

        return back()->with('success', 'Pengajuan PH berhasil dikirim.');
    }

    public function destroy($id)
    {
        $ph = PublicHolidayRequest::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->firstOrFail();

        $ph->delete();

        return redirect()->back()->with('success', 'Pengajuan PH berhasil dibatalkan');
    }

    public function cancel($id)
    {
        $ph = PublicHolidayRequest::where('user_id', Auth::id())
            ->where('status', 'pending')
            ->findOrFail($id);

        $ph->update([
            'status' => 'cancelled'
        ]);

        return back()->with('success', 'Pengajuan PH dibatalkan.');
    }

    private function buildPublicHolidayMessage($namaStaff, $namaAtasan, $holidayName, $claimDate)
    {
        return
            "📅 *Pengajuan Public Holiday*\n\n" .
            "Nama: {$namaStaff}\n" .
            "PH: {$holidayName}\n" .
            "Tanggal Claim: {$claimDate->format('d-m-Y')}\n\n" .
            "Menunggu persetujuan Anda.";
    }
}
