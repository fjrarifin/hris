<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\HrdAuditLogController;
use App\Http\Controllers\Api\HrApprovalController;
use App\Http\Controllers\Api\HrAttendanceController;
use App\Http\Controllers\Api\HrAttendanceCorrectionController;
use App\Http\Controllers\Api\HrContractController;
use App\Http\Controllers\Api\HrDashboardController;
use App\Http\Controllers\Api\HrJobdeskController;
use App\Http\Controllers\Api\HrKpiTemplateController;
use App\Http\Controllers\Api\HrPerformancePeriodController;
use App\Http\Controllers\Api\HrPerformanceReviewController;
use App\Http\Controllers\Api\HrPayrollMasterController;
use App\Http\Controllers\Api\HrPayrollProcessController;
use App\Http\Controllers\Api\HrScheduleController;
use App\Http\Controllers\Api\HrTalentOptionsController;
use App\Http\Controllers\Api\ItUserController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnlineUserController;
use App\Http\Controllers\Api\StaffPerformanceReviewController;
use App\Http\Controllers\Api\StaffPortalController;
use App\Http\Controllers\Api\StaffTalentController;
use App\Http\Controllers\Api\StaffTeamScheduleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceWebhookController;
use App\Http\Controllers\FingerspotController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\WhatsAppAiAgentWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/rfid', [RfidController::class, 'scan'])->withoutMiddleware(['auth']);
Route::get('/rfid', [RfidController::class, 'last'])->withoutMiddleware(['auth']);

Route::post('/attendance/webhook', [AttendanceWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth']);

Route::post('/whatsapp/ai-agent/webhook', WhatsAppAiAgentWebhookController::class)
    ->middleware('throttle:30,1')
    ->withoutMiddleware(['auth']);

Route::prefix('fingerspot')->group(function () {
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
    Route::get('/attendance/pull', [AttendanceController::class, 'pull'])->middleware('level:0,1,2');

    Route::get('/profile-photos/{filename}', [StaffPortalController::class, 'profilePhoto'])
        ->where('filename', '[A-Za-z0-9_-]+\.(?:jpg|jpeg|png)')
        ->name('profile-photos.show');

    Route::prefix('fingerspot')->middleware('level:0,2')->group(function () {
        Route::post('/get-attlog', [FingerspotController::class, 'getAttlog']);
        Route::post('/get-userinfo', [FingerspotController::class, 'getUserinfo']);
        Route::post('/set-userinfo', [FingerspotController::class, 'setUserinfo']);
        Route::post('/delete-userinfo', [FingerspotController::class, 'deleteUserinfo']);
        Route::post('/get-all-pin', [FingerspotController::class, 'getAllPin']);
        Route::post('/set-time', [FingerspotController::class, 'setTime']);
        Route::post('/register-online', [FingerspotController::class, 'registerOnline']);
        Route::post('/restart-machine', [FingerspotController::class, 'restartMachine']);
        Route::post('/get-device', [FingerspotController::class, 'getDevice']);
    });

    Route::get('/auth/me', [ApiAuthController::class, 'me']);
    Route::post('/auth/change-password', [ApiAuthController::class, 'changePassword']);
    Route::post('/auth/logout', [ApiAuthController::class, 'logout']);

    Route::middleware('password.changed.api')->group(function () {
        Route::get('/navigation', [NavigationController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mobile-token', [NotificationController::class, 'registerMobileToken']);
        Route::delete('/notifications/mobile-token', [NotificationController::class, 'unregisterMobileToken']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead']);
        Route::get('/online-users', [OnlineUserController::class, 'index']);
        Route::post('/online-users/heartbeat', [OnlineUserController::class, 'heartbeat']);
        Route::prefix('navigation/access')->middleware('level:0')->group(function () {
            Route::get('/', [NavigationController::class, 'access']);
            Route::put('/{frontendMenu}', [NavigationController::class, 'update']);
            Route::put('/{frontendMenu}/users/{user}', [NavigationController::class, 'updateUserAccess']);
        });
        Route::get('/audit-logs', [HrdAuditLogController::class, 'index'])
            ->middleware(['level:0', 'frontend.menu:audit-logs']);
        Route::prefix('it/users')
            ->middleware(['level:0', 'frontend.menu:user-management'])
            ->group(function () {
                Route::get('/', [ItUserController::class, 'index']);
                Route::put('/{user}', [ItUserController::class, 'update']);
                Route::post('/{user}/reset-password', [ItUserController::class, 'resetPassword']);
                Route::post('/{user}/reset-photo-limit', [ItUserController::class, 'resetPhotoLimit']);
                Route::post('/{user}/reset-email-limit', [ItUserController::class, 'resetEmailLimit']);
                Route::post('/{user}/reset-password-limit', [ItUserController::class, 'resetPasswordLimit']);
            });

        Route::middleware('frontend.menu:employees')->group(function () {
            Route::get('/employee/fingerspot/clouds', [EmployeeController::class, 'fingerspotClouds']);
            Route::post('/employee/{employee}/fingerspot-userinfo', [EmployeeController::class, 'sendFingerspotUserinfo']);
            Route::apiResource('employee', EmployeeController::class);
            Route::get('/employees', [EmployeeController::class, 'frontendIndex']);
            Route::get('/employees/export', [EmployeeController::class, 'export']);
        });

        Route::prefix('hr')->middleware('level:2')->group(function () {
            Route::get('/dashboard', HrDashboardController::class);
            Route::middleware('frontend.menu:hr-attendance-corrections')->group(function () {
                Route::get('/attendance-corrections', [HrAttendanceCorrectionController::class, 'index']);
                Route::put('/attendance-corrections/{nik}', [HrAttendanceCorrectionController::class, 'store']);
            });
            Route::middleware('frontend.menu:hr-contracts')->group(function () {
                Route::get('/contracts', [HrContractController::class, 'index']);
                Route::get('/contracts/{nik}', [HrContractController::class, 'show']);
                Route::post('/contracts/{nik}', [HrContractController::class, 'store']);
                Route::put('/contracts/records/{contractId}', [HrContractController::class, 'update']);
                Route::get('/contracts/records/{contractId}/pdf-preview', [HrContractController::class, 'previewPdf']);
            });
            Route::get('/approvals/{type}', [HrApprovalController::class, 'index']);
            Route::post('/approvals/{type}/{id}', [HrApprovalController::class, 'decide']);
            Route::post('/approvals/{type}/{id}/cancel', [HrApprovalController::class, 'cancel']);
            Route::get('/schedules/options', [HrScheduleController::class, 'options']);
            Route::get('/schedules/template', [HrScheduleController::class, 'template']);
            Route::get('/schedules', [HrScheduleController::class, 'index']);
            Route::get('/schedules/department', [HrScheduleController::class, 'department']);
            Route::post('/schedules/upload', [HrScheduleController::class, 'upload']);
            Route::get('/schedules/employees/{nik}', [HrScheduleController::class, 'employee']);
            Route::put('/schedules/employees/{nik}', [HrScheduleController::class, 'store']);
            Route::prefix('talent')->group(function () {
                Route::get('/options', HrTalentOptionsController::class);
                Route::apiResource('jobdesks', HrJobdeskController::class)->except(['show']);
                Route::get('/jobdesks/{jobdesk}/pdf-preview', [HrJobdeskController::class, 'previewPdf']);
                Route::post('/kpis/jabatans/{jabatan}/sync-active', [HrKpiTemplateController::class, 'syncActive']);
                Route::apiResource('kpis', HrKpiTemplateController::class)->parameters(['kpis' => 'kpiTemplate'])->except(['show']);
                Route::apiResource('periods', HrPerformancePeriodController::class)->parameters(['periods' => 'performancePeriod'])->only(['index', 'store', 'update']);
                Route::get('/reviews', [HrPerformanceReviewController::class, 'index']);
                Route::post('/reviews', [HrPerformanceReviewController::class, 'store']);
                Route::get('/reviews/{performanceReview}', [HrPerformanceReviewController::class, 'show']);
                Route::patch('/reviews/{performanceReview}/status', [HrPerformanceReviewController::class, 'updateStatus']);
            });
        });

        Route::get('/hr/attendance', [HrAttendanceController::class, 'index'])
            ->middleware(['level:1,2', 'frontend.menu:attendance']);
        Route::get('/hr/attendance/options', [HrAttendanceController::class, 'options'])
            ->middleware(['level:1,2', 'frontend.menu:attendance']);
        Route::get('/hr/attendance/export', [HrAttendanceController::class, 'export'])
            ->middleware(['level:1,2', 'frontend.menu:attendance']);
        Route::get('/hr/attendance/minimum-monitoring', [HrAttendanceController::class, 'minimumMonitoring'])
            ->middleware(['level:2', 'frontend.menu:hr-attendance-minimum']);
        Route::get('/hr/attendance/minimum-monitoring/export', [HrAttendanceController::class, 'exportMinimumMonitoring'])
            ->middleware(['level:2', 'frontend.menu:hr-attendance-minimum']);
        Route::post('/hr/attendance/minimum-monitoring/notify', [HrAttendanceController::class, 'notifyMinimumMonitoringEmployee'])
            ->middleware(['level:2', 'frontend.menu:hr-attendance-minimum']);
        Route::post('/hr/attendance/minimum-monitoring/notify-bulk', [HrAttendanceController::class, 'notifyMinimumMonitoringEmployees'])
            ->middleware(['level:2', 'frontend.menu:hr-attendance-minimum']);
        Route::prefix('hr/payroll')->middleware(['level:1,2', 'frontend.menu:hr-payroll-master'])->group(function () {
            Route::get('/master', [HrPayrollMasterController::class, 'index']);
            Route::get('/master/components', [HrPayrollMasterController::class, 'components']);
            Route::put('/master/components/{payrollComponent}', [HrPayrollMasterController::class, 'updateComponent']);
            Route::get('/master/{nik}', [HrPayrollMasterController::class, 'show']);
            Route::put('/master/{nik}', [HrPayrollMasterController::class, 'update']);
        });
        Route::prefix('hr/payroll/process')->middleware(['level:1,2', 'frontend.menu:hr-payroll-process'])->group(function () {
            Route::get('/periods', [HrPayrollProcessController::class, 'periods']);
            Route::get('/preview', [HrPayrollProcessController::class, 'preview']);
            Route::post('/preview/auto-correct', [HrPayrollProcessController::class, 'autoCorrect']);
            Route::post('/generate', [HrPayrollProcessController::class, 'generate']);
            Route::get('/drafts', [HrPayrollProcessController::class, 'drafts']);
            Route::get('/drafts/export', [HrPayrollProcessController::class, 'exportDrafts']);
            Route::get('/drafts/{payroll}', [HrPayrollProcessController::class, 'show']);
            Route::put('/drafts/{payroll}/adjustments', [HrPayrollProcessController::class, 'updateAdjustments']);
            Route::post('/drafts/{payroll}/submit', [HrPayrollProcessController::class, 'submit']);
            Route::post('/drafts/{payroll}/approve', [HrPayrollProcessController::class, 'approve']);
            Route::post('/drafts/{payroll}/cancel-submit', [HrPayrollProcessController::class, 'cancelSubmit']);
            Route::post('/drafts/{payroll}/cancel-approve', [HrPayrollProcessController::class, 'cancelApprove']);
            Route::post('/drafts/{payroll}/lock', [HrPayrollProcessController::class, 'lock']);
            Route::post('/drafts/{payroll}/artifact', [HrPayrollProcessController::class, 'downloadSlip']);
            Route::get('/drafts/{payroll}/pdf-download', [HrPayrollProcessController::class, 'downloadSlip']);
            Route::get('/drafts/{payroll}/slip', [HrPayrollProcessController::class, 'downloadSlip']);
            Route::post('/drafts/{payroll}/send-slip', [HrPayrollProcessController::class, 'sendSlip']);
        });

        Route::prefix('staff')->middleware('level:3')->group(function () {
            Route::get('/dashboard', [StaffPortalController::class, 'dashboard']);
            Route::get('/profile', [StaffPortalController::class, 'profile']);
            Route::get('/employees/search', [StaffPortalController::class, 'searchEmployees']);
            Route::get('/employees/{nik}/profile', [StaffPortalController::class, 'employeeProfile']);
            Route::patch('/profile/contact', [StaffPortalController::class, 'updateProfileContact']);
            Route::post('/profile/contact/phone-otp', [StaffPortalController::class, 'requestProfilePhoneOtp'])
                ->middleware('throttle:5,1');
            Route::post('/profile/photo', [StaffPortalController::class, 'updateProfilePhoto']);

            Route::middleware('frontend.menu:staff-attendance')->group(function () {
                Route::get('/attendance', [StaffPortalController::class, 'attendance']);
                Route::post('/attendance/selfie', [StaffPortalController::class, 'storeSelfAttendance']);
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

            Route::middleware('frontend.menu:staff-extra-off')->group(function () {
                Route::get('/extra-off', [StaffPortalController::class, 'extraOffs']);
                Route::post('/extra-off', [StaffPortalController::class, 'storeExtraOff']);
                Route::delete('/extra-off/{extraOffRequest}', [StaffPortalController::class, 'destroyExtraOff']);
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
            Route::post('/dashboard/absence-cancellation-notification', [StaffPortalController::class, 'notifyHrAbsenceCancellation']);

            Route::middleware('frontend.menu:staff-overtime')->group(function () {
                Route::get('/overtime', [StaffPortalController::class, 'overtime']);
                Route::post('/overtime', [StaffPortalController::class, 'storeOvertime']);
                Route::delete('/overtime/{overtimeRequest}', [StaffPortalController::class, 'destroyOvertime']);
            });

            Route::prefix('team-schedules')->middleware('frontend.menu:staff-team-schedules')->group(function () {
                Route::get('/', [StaffTeamScheduleController::class, 'index']);
                Route::get('/template', [StaffTeamScheduleController::class, 'template']);
                Route::post('/upload', [StaffTeamScheduleController::class, 'upload']);
                Route::get('/employees/{nik}', [StaffTeamScheduleController::class, 'employee']);
                Route::put('/employees/{nik}', [StaffTeamScheduleController::class, 'store']);
            });

            Route::prefix('performance-reviews')->middleware('frontend.menu:staff-performance-reviews')->group(function () {
                Route::get('/', [StaffPerformanceReviewController::class, 'index']);
                Route::get('/{performanceReview}', [StaffPerformanceReviewController::class, 'show']);
                Route::put('/{performanceReview}', [StaffPerformanceReviewController::class, 'update']);
                Route::post('/{performanceReview}/submit', [StaffPerformanceReviewController::class, 'submit']);
            });

            Route::prefix('talent')->middleware('frontend.menu:staff-talent')->group(function () {
                Route::get('/', [StaffTalentController::class, 'index']);
                Route::get('/jobdesks/{jobdesk}/pdf-preview', [StaffTalentController::class, 'previewPdf']);
            });
        });
    });
});
