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
     * 1. Hak cuti HANYA didapatkan jika karyawan sudah mencapai masa kerja >= 1 tahun dari join_date.
     * 2. Jika sudah >= 1 tahun, penambahan saldo cuti dihitung mulai dari kontrak aktif terakhir (start_date kontrak aktif), bertambah +1 setiap bulan tepat pada tanggal join_date.
     * 3. Tanggal kedaluwarsa (expired_at) seluruh saldo cuti mengikuti tanggal akhir (end_date) kontrak aktif terakhir.
     */
    public function generateMonthly(User $user, bool $forceReset = false)
    {
        $karyawan = $user->karyawan;

        if (! $karyawan || ! $karyawan->join_date) {
            // Hapus accrual otomatis jika data karyawan / join_date tidak valid
            LeaveAccrual::query()
                ->where('user_id', $user->id)
                ->whereNull('notes')
                ->delete();
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

        // Tentukan tanggal awal dan akhir kontrak aktif
        $contractStart = $activeContract && ! empty($activeContract->start_date)
            ? Carbon::parse($activeContract->start_date)->startOfDay()
            : $joinDate;

        $expiredAt = null;
        if ($activeContract && ! empty($activeContract->end_date)) {
            $expiredAt = Carbon::parse($activeContract->end_date)->startOfDay();
        } elseif (! empty($karyawan->end_date)) {
            $expiredAt = Carbon::parse($karyawan->end_date)->startOfDay();
        }

        // Syarat 1: Validasi masa kerja >= 1 tahun dari join_date
        // Jika belum mencapai 1 tahun dari join date, belum berhak mendapatkan cuti apapun
        if ($today->lt($oneYearAnniversary)) {
            // Bersihkan accrual otomatis yang mungkin pernah terbuat keliru
            LeaveAccrual::query()
                ->where('user_id', $user->id)
                ->whereNull('notes')
                ->delete();
            return;
        }

        // Syarat 2: Tanggal mulai penambahan saldo dihitung dari kontrak aktif terakhir
        // Jika kontrak aktif dimulai setelah 1 tahun masa kerja (misal perpanjangan kontrak),
        // maka accrual dimulai dari bulan dimulainya kontrak aktif tersebut.
        // Jika 1 tahun masa kerja baru tercapai di tengah kontrak aktif, mulai dari 1 bulan setelah 1 tahun masa kerja.
        if ($contractStart->greaterThanOrEqualTo($oneYearAnniversary)) {
            $startCalculationMonth = $contractStart->copy()->startOfMonth();
        } else {
            $startCalculationMonth = $oneYearAnniversary->copy()->addMonth()->startOfMonth();
        }

        // Bersihkan accrual otomatis lama di luar rentang kontrak aktif ini agar saldo masa lalu tidak menumpuk
        LeaveAccrual::query()
            ->where('user_id', $user->id)
            ->whereNull('notes')
            ->where(function ($q) use ($startCalculationMonth, $contractStart) {
                $q->whereDate('accrued_at', '<', $contractStart->toDateString())
                  ->orWhere('year', '<', $startCalculationMonth->year)
                  ->orWhere(function ($sub) use ($startCalculationMonth) {
                      $sub->where('year', '=', $startCalculationMonth->year)
                          ->where('month', '<', $startCalculationMonth->month);
                  });
            })
            ->delete();

        // Sinkronisasi expired_at untuk seluruh saldo cuti otomatis yang ada
        if ($expiredAt) {
            LeaveAccrual::query()
                ->where('user_id', $user->id)
                ->whereNull('notes')
                ->whereDate('expired_at', '!=', $expiredAt->toDateString())
                ->update([
                    'expired_at' => $expiredAt->toDateString(),
                ]);
        }

        // Loop setiap bulan dari awal periode kontrak aktif hingga bulan ini
        $cursorMonth = $startCalculationMonth->copy();
        $currentMonth = $today->copy()->startOfMonth();

        while ($cursorMonth->lessThanOrEqualTo($currentMonth)) {
            $daysInMonth = $cursorMonth->daysInMonth;
            $day = min($targetDay, $daysInMonth);
            $accrualDate = $cursorMonth->copy()->day($day)->startOfDay();

            // Hanya accrue jika tanggal accrual sudah lewat atau hari ini
            if ($accrualDate->greaterThan($today)) {
                break;
            }

            // Pastikan tanggal accrual berada pada atau setelah contractStart
            if ($accrualDate->greaterThanOrEqualTo($contractStart)) {
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
            }

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
