<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LeaveAccrual;
use App\Models\PublicHoliday;
use App\Models\PublicHolidayRequest;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $today = now();

        /*
        |--------------------------------------------------------------------------
        | SALDO CUTI
        |--------------------------------------------------------------------------
        */
        $leaveBalance = LeaveAccrual::where('user_id', $user->id)
            ->where('is_used', false)
            ->where('expired_at', '>=', $today)
            ->count();

        /*
        |--------------------------------------------------------------------------
        | SALDO PUBLIC HOLIDAY
        |--------------------------------------------------------------------------
        */

        $year = now()->year;

        /*
        |--------------------------------------------------------------------------
        | TOTAL PH TAHUN INI
        |--------------------------------------------------------------------------
        */
        $approvedByManagerIds = PublicHolidayRequest::where('user_id', Auth::id())
            ->whereNotNull('manager_approved_at')
            ->where('status', 'approved')
            ->pluck('public_holiday_id');

        $totalPH = PublicHoliday::where('is_active', true)
            ->whereDate('holiday_date', '<', now())
            ->whereDate('holiday_date', '>', now()->subDays(90))
            ->whereNotIn('id', $approvedByManagerIds)
            ->orderBy('holiday_date', 'desc')
            ->count();

        // dd($totalPH);

        $publicHolidayBalance = $totalPH;

        return view('staff.dashboard', compact(
            'leaveBalance',
            'publicHolidayBalance'
        ));
    }
}
