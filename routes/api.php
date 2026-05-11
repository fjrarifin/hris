<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\AttendanceWebhookController;
use App\Http\Controllers\AttendanceController;

Route::post('/rfid', [RfidController::class, 'scan'])->withoutMiddleware(['auth']);
Route::get('/rfid', [RfidController::class, 'last'])->withoutMiddleware(['auth']);

Route::post('/attendance/webhook', [AttendanceWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth']);

Route::get('/attendance/pull', [AttendanceController::class, 'pull']);
