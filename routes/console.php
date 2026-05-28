<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('fingerspot:sync-attendance')
    ->dailyAt('23:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('attendance:send-incomplete-report')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('attendance:send-employee-warnings')
    ->dailyAt('07:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('absence:auto-cancel-attended')
    ->dailyAt('07:45')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('contracts:expire')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->runInBackground();
