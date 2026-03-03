<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\LeaveAccrual;
use Carbon\Carbon;

class GenerateLeaveAccruals extends Command
{
    protected $signature = 'leave:generate';
    protected $description = 'Generate leave accrual from join date until now';

    public function handle()
    {
        $users = User::with('karyawan')->get();

        foreach ($users as $user) {

            if (!$user->karyawan || !$user->karyawan->join_date) {
                continue;
            }

            $joinDate = Carbon::parse($user->karyawan->join_date);
            $eligibleDate = $joinDate->copy()->addYear();
            $now = now();

            if ($eligibleDate->gt($now)) {
                continue;
            }

            $period = $eligibleDate->copy()->startOfMonth();

            while ($period->lte($now)) {

                $accruedAt = $period->copy()->day($joinDate->day);

                // Jangan generate kalau accruedAt masih di masa depan
                if ($accruedAt->gt($now)) {
                    $period->addMonth();
                    continue;
                }

                $expiredAt = $accruedAt->copy()->addDays(365);

                LeaveAccrual::firstOrCreate([
                    'user_id' => $user->id,
                    'year' => $period->year,
                    'month' => $period->month,
                ], [
                    'nik' => $user->karyawan->nik,
                    'accrued_at' => $accruedAt,
                    'days' => 1,
                    'expired_at' => $expiredAt,
                ]);

                $period->addMonth();
            }
        }

        $this->info('Leave accrual generated successfully.');
    }
}
