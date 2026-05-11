<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Http\Controllers\LeaveAccrualService;

class GenerateLeaveAccruals extends Command
{
    protected $signature = 'leave:generate';
    protected $description = 'Generate leave accrual for all employees';

    public function handle()
    {
        $service = new LeaveAccrualService();

        $users = User::with('karyawan')->get();

        foreach ($users as $user) {

            $service->generateMonthly($user);
        }

        $this->info('Leave accrual generated successfully');
    }
}
