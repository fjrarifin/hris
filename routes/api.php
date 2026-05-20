<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\AttendanceWebhookController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\FingerspotController;

Route::post('/rfid', [RfidController::class, 'scan'])->withoutMiddleware(['auth']);
Route::get('/rfid', [RfidController::class, 'last'])->withoutMiddleware(['auth']);

Route::post('/attendance/webhook', [AttendanceWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth']);

Route::get('/attendance/pull', [AttendanceController::class, 'pull']);

Route::prefix('fingerspot')->group(function () {
    Route::post('/get-attlog', [FingerspotController::class, 'getAttlog']);
    Route::post('/get-userinfo', [FingerspotController::class, 'getUserinfo']);
    Route::post('/set-userinfo', [FingerspotController::class, 'setUserinfo']);
    Route::post('/delete-userinfo', [FingerspotController::class, 'deleteUserinfo']);
    Route::post('/get-all-pin', [FingerspotController::class, 'getAllPin']);
    Route::post('/set-time', [FingerspotController::class, 'setTime']);
    Route::post('/register-online', [FingerspotController::class, 'registerOnline']);
    Route::post('/restart-machine', [FingerspotController::class, 'restartMachine']);
    Route::post('/get-device', [FingerspotController::class, 'getDevice']);

    Route::post('/webhook', [FingerspotController::class, 'webhook']);
});