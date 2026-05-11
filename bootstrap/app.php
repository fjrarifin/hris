<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\CheckLevel;
use App\Http\Middleware\ForceChangePassword;
use App\Http\Controllers\LeaveAccrualService;
use App\Models\User;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'level' => CheckLevel::class,
            'force.password' => ForceChangePassword::class,
        ]);
    })
    ->withCommands([
        \App\Console\Commands\AutoRejectExpiredLeave::class,
    ])
    ->withSchedule(function ($schedule) {
        $schedule->command('leave:auto-reject')
            ->dailyAt('00:05');
        $schedule->command('app:auto-reject-expired-public-holiday')
            ->dailyAt('00:05');
        $schedule->call(function () {
            $users = User::with('karyawan')->get();

            foreach ($users as $user) {
                app(LeaveAccrualService::class)->generateMonthly($user);
            }
        })->dailyAt('00:10');
    })
    // ->withSchedule(function ($schedule) {
    //     $schedule->command('leave:auto-reject')->everyMinute();
    // })


    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
