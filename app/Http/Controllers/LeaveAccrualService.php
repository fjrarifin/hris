<?php

namespace App\Http\Controllers;

use App\Models\LeaveAccrual;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService extends Controller
{
    /**
     * Generate monthly leave accruals for an employee based on company rules:
     * 1. Hak cuti didapatkan setelah masa kerja mencapai minimal 1 tahun penuh dari join_date.
     * 2. Saldo cuti didapatkan bertahap +1 setiap bulan tepat pada tanggal join date (mulai bulan ke-13).
     * 3. Tanggal kedaluwarsa (expired_at) seluruh saldo cuti mengikuti tanggal akhir kontrak aktif terakhir.
     */
    public function generateMonthly(User $user)
    {
        $karyawan = $user->karyawan;

        if (! $karyawan || ! $karyawan->join_date) {
            return;
        }

        $joinDate = Carbon::parse($karyawan->join_date)->startOfDay();
        $oneYearAnniversary = $joinDate->copy()->addYear();
        $targetDay = (int) $joinDate->day;
        $today = now()->startOfDay();

        // Cari kontrak aktif karyawan
        $activeContract = DB::table('t_kontrak_karyawan')
            ->where('nik', $karyawan->nik)
            ->where('status_kontrak', 'AKTIF')
            ->orderByDesc('start_date')
            ->first();

        // Fallback jika tidak ada status AKTIF eksplisit, ambil kontrak dengan tanggal berakhir paling baru
        if (! $activeContract) {
            $activeContract = DB::table('t_kontrak_karyawan')
                ->where('nik', $karyawan->nik)
                ->orderByDesc('end_date')
                ->first();
        }

        // Tentukan tanggal expired cuti (akhir kontrak aktif terakhir)
        $expiredAt = null;
        if ($activeContract && ! empty($activeContract->end_date)) {
            $expiredAt = Carbon::parse($activeContract->end_date)->startOfDay();
        } elseif (! empty($karyawan->end_date)) {
            $expiredAt = Carbon::parse($karyawan->end_date)->startOfDay();
        }

        // Sinkronisasi expired_at untuk saldo cuti aktif yang ada jika kontrak aktif memiliki end_date
        if ($expiredAt) {
            LeaveAccrual::query()
                ->where('user_id', $user->id)
                ->where('is_used', false)
                ->whereNull('notes') // Hanya update accrual otomatis bulanan, bukan adjustment manual
                ->whereDate('expired_at', '!=', $expiredAt->toDateString())
                ->update([
                    'expired_at' => $expiredAt->toDateString(),
                ]);
        }

        // Syarat 1: Jika belum genap 1 tahun (atau belum mencapai bulan ke-13 setelah 1 tahun), belum berhak dapat cuti
        // Tanggal accrual pertama adalah 1 bulan setelah genap 1 tahun (bulan ke-13 dari join date)
        $firstAccrualMonth = $oneYearAnniversary->copy()->addMonth()->startOfMonth();
        $firstAccrualDay = min($targetDay, $firstAccrualMonth->daysInMonth);
        $firstAccrualDate = $firstAccrualMonth->copy()->day($firstAccrualDay)->startOfDay();

        if ($today->lt($firstAccrualDate)) {
            return;
        }

        // Hitung dan generate accrual bulanan sejak bulan ke-13 sampai hari ini
        // Loop setiap bulan dari firstAccrualMonth hingga bulan saat ini
        $cursorMonth = $firstAccrualMonth->copy();
        $currentMonth = $today->copy()->startOfMonth();

        while ($cursorMonth->lessThanOrEqualTo($currentMonth)) {
            $daysInMonth = $cursorMonth->daysInMonth;
            $day = min($targetDay, $daysInMonth);
            $accrualDate = $cursorMonth->copy()->day($day)->startOfDay();

            // Hanya accrue jika tanggal accrual sudah lewat atau hari ini
            if ($accrualDate->greaterThan($today)) {
                break;
            }

            // Tentukan expired date untuk accrual ini
            $itemExpiredAt = $expiredAt ? $expiredAt->toDateString() : $accrualDate->copy()->addYear()->toDateString();

            LeaveAccrual::firstOrCreate([
                'user_id' => $user->id,
                'year' => $accrualDate->year,
                'month' => $accrualDate->month,
            ], [
                'nik' => $karyawan->nik,
                'accrued_at' => $accrualDate->toDateString(),
                'days' => 1,
                'expired_at' => $itemExpiredAt,
            ]);

            $cursorMonth->addMonth();
        }
    }

    public function getBalance(User $user)
    {
        $today = now()->startOfDay();

        return (int) LeaveAccrual::where('user_id', $user->id)
            ->whereDate('expired_at', '>=', $today)
            ->where('is_used', false)
            ->sum('days');
    }
}
