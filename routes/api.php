<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\HrApprovalController;
use App\Http\Controllers\Api\HrAttendanceController;
use App\Http\Controllers\Api\HrDashboardController;
use App\Http\Controllers\Api\HrScheduleController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\StaffPortalController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceWebhookController;
use App\Http\Controllers\FingerspotController;
use App\Http\Controllers\RfidController;
use Illuminate\Support\Facades\Route;

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

Route::post('/auth/login', [ApiAuthController::class, 'login']);
Route::post('/auth/forgot-password/request-otp', [ForgotPasswordController::class, 'requestOtp'])
    ->middleware('throttle:5,1');
Route::post('/auth/forgot-password/verify-otp', [ForgotPasswordController::class, 'verifyOtp'])
    ->middleware('throttle:10,1');
Route::post('/auth/forgot-password/reset', [ForgotPasswordController::class, 'resetPassword'])
    ->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [ApiAuthController::class, 'me']);
    Route::post('/auth/change-password', [ApiAuthController::class, 'changePassword']);
    Route::post('/auth/logout', [ApiAuthController::class, 'logout']);

    Route::middleware('password.changed.api')->group(function () {
        Route::get('/navigation', [NavigationController::class, 'index']);
        Route::prefix('navigation/access')->middleware('level:0')->group(function () {
            Route::get('/', [NavigationController::class, 'access']);
            Route::put('/{frontendMenu}', [NavigationController::class, 'update']);
            Route::put('/{frontendMenu}/users/{user}', [NavigationController::class, 'updateUserAccess']);
        });

        Route::middleware('frontend.menu:employees')->group(function () {
            Route::apiResource('employee', EmployeeController::class);
            Route::get('/employees', [EmployeeController::class, 'frontendIndex']);
            Route::get('/employees/export', [EmployeeController::class, 'export']);
        });

        Route::prefix('hr')->middleware('level:2')->group(function () {
            Route::get('/dashboard', HrDashboardController::class);
            Route::get('/approvals/{type}', [HrApprovalController::class, 'index']);
            Route::post('/approvals/{type}/{id}', [HrApprovalController::class, 'decide']);
            Route::post('/approvals/{type}/{id}/cancel', [HrApprovalController::class, 'cancel']);
            Route::get('/schedules', [HrScheduleController::class, 'index']);
            Route::get('/schedules/department', [HrScheduleController::class, 'department']);
            Route::post('/schedules/upload', [HrScheduleController::class, 'upload']);
            Route::get('/schedules/employees/{nik}', [HrScheduleController::class, 'employee']);
            Route::put('/schedules/employees/{nik}', [HrScheduleController::class, 'store']);
        });

        Route::get('/hr/attendance', [HrAttendanceController::class, 'index'])
            ->middleware(['level:1,2', 'frontend.menu:attendance']);
        Route::get('/hr/attendance/options', [HrAttendanceController::class, 'options'])
            ->middleware(['level:1,2', 'frontend.menu:attendance']);
        Route::get('/hr/attendance/export', [HrAttendanceController::class, 'export'])
            ->middleware(['level:1,2', 'frontend.menu:attendance']);

        Route::prefix('staff')->middleware('level:3')->group(function () {
            Route::get('/dashboard', [StaffPortalController::class, 'dashboard']);
            Route::get('/profile', [StaffPortalController::class, 'profile']);
            Route::post('/profile/photo', [StaffPortalController::class, 'updateProfilePhoto']);

            Route::middleware('frontend.menu:staff-attendance')->group(function () {
                Route::get('/attendance', [StaffPortalController::class, 'attendance']);
            });

            Route::middleware('frontend.menu:staff-leave')->group(function () {
                Route::get('/leave', [StaffPortalController::class, 'leaves']);
                Route::post('/leave', [StaffPortalController::class, 'storeLeave']);
                Route::delete('/leave/{leaveRequest}', [StaffPortalController::class, 'destroyLeave']);
            });

            Route::middleware('frontend.menu:staff-public-holiday')->group(function () {
                Route::get('/public-holiday', [StaffPortalController::class, 'publicHolidays']);
                Route::post('/public-holiday', [StaffPortalController::class, 'storePublicHoliday']);
                Route::delete('/public-holiday/{publicHolidayRequest}', [StaffPortalController::class, 'destroyPublicHoliday']);
            });

            Route::middleware('frontend.menu:staff-permission')->group(function () {
                Route::get('/permission', [StaffPortalController::class, 'permissions']);
                Route::post('/permission', [StaffPortalController::class, 'storePermission']);
                Route::delete('/permission/{employeePermission}', [StaffPortalController::class, 'destroyPermission']);
            });

            Route::middleware('frontend.menu:staff-approvals')->group(function () {
                Route::get('/approvals', [StaffPortalController::class, 'approvals']);
                Route::post('/approvals/{type}/{id}', [StaffPortalController::class, 'decideApproval']);
            });

            Route::middleware('frontend.menu:staff-overtime')->group(function () {
                Route::get('/overtime', [StaffPortalController::class, 'overtime']);
                Route::post('/overtime', [StaffPortalController::class, 'storeOvertime']);
                Route::delete('/overtime/{overtimeRequest}', [StaffPortalController::class, 'destroyOvertime']);
            });
        });
    });
});
