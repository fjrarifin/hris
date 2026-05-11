<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $today = now();

        $joinDate = Carbon::parse($karyawan->join_date);

        // join + 1 tahun
        $eligibleJoinDate = $joinDate->copy()->addYear();

        // ambil kontrak aktif
        $kontrak = DB::table('t_kontrak_karyawan')
            ->where('nik', $karyawan->nik)
            ->where('status_kontrak', 'AKTIF')
            ->orderByDesc('start_date')
            ->first();

        if (!$kontrak) {
            return;
        }

        $kontrakStart = Carbon::parse($kontrak->start_date);

        // tanggal mulai accrual
        $startAccrual = $eligibleJoinDate->greaterThan($kontrakStart)
            ? $eligibleJoinDate
            : $kontrakStart;

        // kalau belum waktunya cuti
        if ($today->lt($startAccrual)) {
            return;
        }

        // hitung jumlah bulan sejak accrual dimulai
        $months = $startAccrual->diffInMonths($today);

        if ($months <= 0) {
            return;
        }

        // maksimal 12 cuti
        $months = min($months, 12);

        for ($i = 0; $i < $months; $i++) {

            $accruedAt = $startAccrual->copy()->addMonths($i);

            LeaveAccrual::firstOrCreate([
                'user_id' => $user->id,
                'year' => $accruedAt->year,
                'month' => $accruedAt->month,
            ], [
                'nik' => $karyawan->nik,
                'accrued_at' => $accruedAt,
                'days' => 1,
                'expired_at' => $accruedAt->copy()->addYear(),
            ]);
        }
    }

    public function getBalance(User $user)
    {
        $today = now();

        return LeaveAccrual::where('user_id', $user->id)
            ->where('expired_at', '>=', $today)
            ->where('is_used', false)
            ->sum('days');
    }
}
