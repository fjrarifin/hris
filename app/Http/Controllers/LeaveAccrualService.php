<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveAccrual;
use App\Models\User;
use Carbon\Carbon;

class LeaveAccrualService extends Controller
{
    public function generateMonthly(User $user)
    {
        $karyawan = $user->karyawan;

        if (!$karyawan || !$karyawan->join_date) {
            return;
        }

        $joinDate = Carbon::parse($karyawan->join_date);
        $today = now();

        $eligibleDate = $joinDate->copy()->addYear();

        if ($today->lt($eligibleDate)) {
            return;
        }

        // Jangan generate kalau belum tanggal join di bulan ini
        if ($today->day < $joinDate->day) {
            return;
        }

        $accruedAt = Carbon::create(
            $today->year,
            $today->month,
            $joinDate->day
        );

        LeaveAccrual::firstOrCreate([
            'user_id' => $user->id,
            'year' => $today->year,
            'month' => $today->month,
        ], [
            'nik' => $karyawan->nik,
            'accrued_at' => $accruedAt,
            'days' => 1,
            'expired_at' => $accruedAt->copy()->addDays(365),
        ]);
    }

    public function getBalance(User $user)
    {
        $today = now();

        return $user->accruals()
            ->where('expired_at', '>=', $today)
            ->where('is_used', false)
            ->count();
    }
}
