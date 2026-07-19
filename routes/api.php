<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\ForgotPasswordController;
use App\Http\Controllers\Api\HrApprovalController;
use App\Http\Controllers\Api\HrAttendanceController;
use App\Http\Controllers\Api\HrAttendanceCorrectionController;
use App\Http\Controllers\Api\HrContractController;
use App\Http\Controllers\Api\HrDashboardController;
use App\Http\Controllers\Api\HrdAuditLogController;
use App\Http\Controllers\Api\HrJobdeskController;
use App\Http\Controllers\Api\HrKpiTemplateController;
use App\Http\Controllers\Api\HrOrgStructureController;
use App\Http\Controllers\Api\HrPayrollMasterController;
use App\Http\Controllers\Api\HrPayrollProcessController;
use App\Http\Controllers\Api\HrPerformancePeriodController;
use App\Http\Controllers\Api\HrPerformanceReviewController;
use App\Http\Controllers\Api\HrRecruitmentCandidateController;
use App\Http\Controllers\Api\HrRecruitmentDashboardController;
use App\Http\Controllers\Api\HrRecruitmentInterviewAgendaController;
use App\Http\Controllers\Api\HrRecruitmentRequestController;
use App\Http\Controllers\Api\HrRecruitmentVacancyController;
use App\Http\Controllers\Api\PublicCareerController;
use App\Http\Controllers\Api\PublicReferenceEvaluationController;
use App\Http\Controllers\Api\HrScheduleController;
use App\Http\Controllers\Api\HrTalentOptionsController;
use App\Http\Controllers\Api\ItActiveSessionController;
use App\Http\Controllers\Api\ItDashboardController;
use App\Http\Controllers\Api\ItPushNotificationController;
use App\Http\Controllers\Api\ItUserController;
use App\Http\Controllers\Api\MobileAppReleaseController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnlineUserController;
use App\Http\Controllers\Api\StaffPerformanceReviewController;
use App\Http\Controllers\Api\StaffPortalController;
use App\Http\Controllers\Api\StaffRecruitmentRequestController;
use App\Http\Controllers\Api\StaffTalentController;
use App\Http\Controllers\Api\StaffTeamScheduleController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceWebhookController;
use App\Http\Controllers\FingerspotController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\WhatsAppAiAgentWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/public/careers/vacancies', [PublicCareerController::class, 'index'])
    ->middleware('throttle:career-read');
Route::get('/public/careers/sitemap.xml', [PublicCareerController::class, 'sitemap'])
    ->middleware('throttle:career-read');
Route::get('/public/careers/robots.txt', [PublicCareerController::class, 'robots'])
    ->middleware('throttle:career-read');
Route::get('/public/careers/vacancies/{slug}', [PublicCareerController::class, 'show'])
    ->middleware('throttle:career-read');
Route::post('/public/careers/vacancies/{slug}/applications', [PublicCareerController::class, 'apply'])
    ->middleware(['throttle:career-apply-ip', 'throttle:career-apply-identity']);
Route::get('/public/reference-check/{type}/{token}', [PublicReferenceEvaluationController::class, 'show'])
    ->whereIn('type', ['staff', 'managerial'])->middleware('throttle:10,1');
Route::post('/public/reference-check/{type}/{token}', [PublicReferenceEvaluationController::class, 'submit'])
    ->whereIn('type', ['staff', 'managerial'])->middleware('throttle:10,1');
Route::get('/public/reference-check-short/{code}', [PublicReferenceEvaluationController::class, 'showShort'])
    ->middleware('throttle:10,1');
Route::post('/public/reference-check-short/{code}', [PublicReferenceEvaluationController::class, 'submitShort'])
    ->middleware('throttle:10,1');

Route::post('/rfid', [RfidController::class, 'scan'])->withoutMiddleware(['auth']);
Route::get('/rfid', [RfidController::class, 'last'])->withoutMiddleware(['auth']);

Route::post('/attendance/webhook', [AttendanceWebhookController::class, 'handle'])
    ->withoutMiddleware(['auth']);

Route::post('/whatsapp/ai-agent/webhook', WhatsAppAiAgentWebhookController::class)
    ->middleware('throttle:30,1')
    ->withoutMiddleware(['auth']);

// Public Candidate Portal Endpoints
Route::get('/public/candidates/reference-check/{token}', [HrRecruitmentCandidateController::class, 'getPublicReferenceCheck'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/candidates/reference-check/{token}', [HrRecruitmentCandidateController::class, 'submitCandidateReferences'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);

Route::get('/public/candidates/offering/{token}', [HrRecruitmentCandidateController::class, 'getPublicOffering'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/candidates/offering/{token}/sign', [HrRecruitmentCandidateController::class, 'submitCandidateOfferingSignature'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);

Route::get('/public/candidates/case-study/{token}', [HrRecruitmentCandidateController::class, 'getPublicCaseStudy'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/candidates/case-study/{token}/submit', [HrRecruitmentCandidateController::class, 'submitPublicCaseStudy'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);

Route::get('/public/pkb/signer/{id}', [HrRecruitmentCandidateController::class, 'getPublicPkbSigner'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/pkb/signer/{id}/sign', [HrRecruitmentCandidateController::class, 'submitPkbSignerSignature'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);

Route::get('/public/candidates/onboarding/{token}', [HrRecruitmentCandidateController::class, 'getPublicOnboarding'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/candidates/onboarding/{token}/submit', [HrRecruitmentCandidateController::class, 'submitCandidateOnboarding'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);

Route::get('/public/candidates/evaluation/{token}', [HrRecruitmentCandidateController::class, 'getPublicEvaluation'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/candidates/evaluation/{token}', [HrRecruitmentCandidateController::class, 'submitPublicEvaluation'])
    ->middleware('throttle:10,1')
    ->withoutMiddleware(['auth']);
Route::post('/public/candidates/evaluation/{token}/resume', [HrRecruitmentCandidateController::class, 'getPublicResumeByEvaluationToken'])
    ->middleware('throttle:10,1')
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
Route::get('/mobile-app/latest', [MobileAppReleaseController::class, 'latest']);
Route::get('/mobile-app/releases/{release}/download', [MobileAppReleaseController::class, 'download'])
    ->name('mobile-app-releases.download');

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
        Route::get('/employee-options', [EmployeeController::class, 'options']);
        Route::get('/navigation', [NavigationController::class, 'index']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mobile-token', [NotificationController::class, 'registerMobileToken']);
        Route::delete('/notifications/mobile-token', [NotificationController::class, 'unregisterMobileToken']);
        Route::post('/notifications/test-push', [NotificationController::class, 'testMobilePush']);
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
        Route::prefix('it/mobile-app-releases')
            ->middleware('level:0')
            ->group(function () {
                Route::get('/', [MobileAppReleaseController::class, 'index']);
                Route::post('/', [MobileAppReleaseController::class, 'store']);
            });
        Route::prefix('it/dashboard')
            ->middleware('level:0')
            ->group(function () {
                Route::get('/', [ItDashboardController::class, 'index']);
                Route::delete('/active-sessions/{token}', [ItDashboardController::class, 'destroySession']);
                Route::delete('/active-sessions/users/{user}', [ItDashboardController::class, 'destroyUser']);
                Route::post('/users/{user}/reset-password', [ItDashboardController::class, 'resetPassword']);
                Route::post('/users/{user}/reset-password-limit', [ItDashboardController::class, 'resetPasswordLimit']);
                Route::post('/users/{user}/reset-photo-limit', [ItDashboardController::class, 'resetPhotoLimit']);
            });
        Route::prefix('it/users')
            ->middleware(['level:0', 'frontend.menu:user-management'])
            ->group(function () {
                Route::get('/', [ItUserController::class, 'index']);
                Route::post('/', [ItUserController::class, 'store']);
                Route::put('/{user}', [ItUserController::class, 'update']);
                Route::post('/{user}/reset-password', [ItUserController::class, 'resetPassword']);
                Route::post('/{user}/reset-photo-limit', [ItUserController::class, 'resetPhotoLimit']);
                Route::post('/{user}/reset-email-limit', [ItUserController::class, 'resetEmailLimit']);
                Route::post('/{user}/reset-password-limit', [ItUserController::class, 'resetPasswordLimit']);
            });
        Route::prefix('it/push-notifications')
            ->middleware(['level:0', 'frontend.menu:it-push-notifications'])
            ->group(function () {
                Route::get('/', [ItPushNotificationController::class, 'index']);
                Route::get('/recipients', [ItPushNotificationController::class, 'recipients']);
                Route::post('/', [ItPushNotificationController::class, 'store']);
            });
        Route::prefix('it/active-sessions')
            ->middleware(['level:0', 'frontend.menu:it-active-sessions'])
            ->group(function () {
                Route::get('/', [ItActiveSessionController::class, 'index']);
                Route::delete('/{token}', [ItActiveSessionController::class, 'destroy']);
                Route::delete('/users/{user}', [ItActiveSessionController::class, 'destroyUser']);
            });

        Route::prefix('it/service-toggles')
            ->middleware(['level:0', 'frontend.menu:it-service-toggles'])
            ->group(function () {
                Route::get('/', [\App\Http\Controllers\Api\CommandServiceToggleController::class, 'index']);
                Route::put('/{commandServiceToggle}', [\App\Http\Controllers\Api\CommandServiceToggleController::class, 'update']);
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

            Route::middleware('frontend.menu:hr-recruitment-vacancies')->group(function () {
                Route::get('recruitment/vacancies/favorite', [HrRecruitmentVacancyController::class, 'favorite']);
                Route::apiResource('recruitment/vacancies', HrRecruitmentVacancyController::class);
            });

            Route::middleware('frontend.menu:hr-recruitment-dashboard')->group(function () {
                Route::get('recruitment/dashboard', HrRecruitmentDashboardController::class);
                Route::get('recruitment/interview-agenda', HrRecruitmentInterviewAgendaController::class);
            });

            Route::middleware('frontend.menu:hr-master-positions')->prefix('master-orgs/positions')->group(function () {
                Route::get('/', [HrOrgStructureController::class, 'index'])->defaults('type', 'positions');
                Route::post('/', [HrOrgStructureController::class, 'store'])->defaults('type', 'positions');
                Route::put('/{id}', [HrOrgStructureController::class, 'update'])->defaults('type', 'positions');
                Route::delete('/{id}', [HrOrgStructureController::class, 'destroy'])->defaults('type', 'positions');
            });

            Route::middleware('frontend.menu:hr-master-divisions')->prefix('master-orgs/divisions')->group(function () {
                Route::get('/', [HrOrgStructureController::class, 'index'])->defaults('type', 'divisions');
                Route::post('/', [HrOrgStructureController::class, 'store'])->defaults('type', 'divisions');
                Route::put('/{id}', [HrOrgStructureController::class, 'update'])->defaults('type', 'divisions');
                Route::delete('/{id}', [HrOrgStructureController::class, 'destroy'])->defaults('type', 'divisions');
            });

            Route::middleware('frontend.menu:hr-master-departments')->prefix('master-orgs/departments')->group(function () {
                Route::get('/', [HrOrgStructureController::class, 'index'])->defaults('type', 'departments');
                Route::post('/', [HrOrgStructureController::class, 'store'])->defaults('type', 'departments');
                Route::put('/{id}', [HrOrgStructureController::class, 'update'])->defaults('type', 'departments');
                Route::delete('/{id}', [HrOrgStructureController::class, 'destroy'])->defaults('type', 'departments');
            });

            Route::middleware('frontend.menu:hr-master-units')->prefix('master-orgs/units')->group(function () {
                Route::get('/', [HrOrgStructureController::class, 'index'])->defaults('type', 'units');
                Route::post('/', [HrOrgStructureController::class, 'store'])->defaults('type', 'units');
                Route::put('/{id}', [HrOrgStructureController::class, 'update'])->defaults('type', 'units');
                Route::delete('/{id}', [HrOrgStructureController::class, 'destroy'])->defaults('type', 'units');
            });

            Route::middleware('frontend.menu:hr-recruitment-candidates')->group(function () {
                Route::apiResource('recruitment/candidates', HrRecruitmentCandidateController::class);
                Route::post('recruitment/candidates/check-conflict', [HrRecruitmentCandidateController::class, 'checkScheduleConflict']);
                Route::post('recruitment/candidates/{candidate}/upload-resume', [HrRecruitmentCandidateController::class, 'uploadResume']);
                Route::get('recruitment/candidates/{candidate}/resume-preview', [HrRecruitmentCandidateController::class, 'previewResume']);
                Route::get('recruitment/candidates/{candidate}/hr-interview-summary-preview', [HrRecruitmentCandidateController::class, 'previewHrInterviewSummary']);
                Route::get('recruitment/candidates/{candidate}/case-study-submission-preview', [HrRecruitmentCandidateController::class, 'previewCaseStudySubmission']);
                Route::post('recruitment/candidates/{candidate}/upload-photo', [HrRecruitmentCandidateController::class, 'uploadPhoto']);
                Route::get('recruitment/candidates/{candidate}/photo', [HrRecruitmentCandidateController::class, 'previewPhoto']);
                Route::post('recruitment/candidates/{candidate}/upload-offering', [HrRecruitmentCandidateController::class, 'uploadOfferingLetter']);
                Route::get('recruitment/candidates/{candidate}/offering-preview', [HrRecruitmentCandidateController::class, 'previewOfferingLetter']);
                Route::post('recruitment/candidates/{candidate}/lock-interview', [HrRecruitmentCandidateController::class, 'lockInterview']);
                Route::post('recruitment/candidates/{candidate}/send-wa-interviewer', [HrRecruitmentCandidateController::class, 'sendWaToInterviewer']);

                // 10-Stage Workflow Endpoints
                Route::post('recruitment/candidates/{candidate}/schedule-hr-interview', [HrRecruitmentCandidateController::class, 'scheduleHrInterview']);
                Route::post('recruitment/candidates/{candidate}/send-wa-candidate-interview', [HrRecruitmentCandidateController::class, 'sendWaToCandidate']);
                Route::post('recruitment/candidates/{candidate}/complete-hr-interview', [HrRecruitmentCandidateController::class, 'completeHrInterview']);
                Route::post('recruitment/candidates/{candidate}/upload-hr-interview-summary', [HrRecruitmentCandidateController::class, 'uploadHrInterviewSummary']);
                Route::post('recruitment/candidates/{candidate}/send-case-study', [HrRecruitmentCandidateController::class, 'sendCaseStudy']);
                Route::post('recruitment/candidates/{candidate}/send-wa-candidate-case-study', [HrRecruitmentCandidateController::class, 'sendWaCaseStudyToCandidate']);
                Route::post('recruitment/candidates/{candidate}/upload-case-study-submission', [HrRecruitmentCandidateController::class, 'uploadCaseStudySubmission']);
                Route::post('recruitment/candidates/{candidate}/schedule-user-interview-round', [HrRecruitmentCandidateController::class, 'scheduleUserInterviewRound']);
                Route::post('recruitment/candidates/{candidate}/user-interview-round/{round}/complete', [HrRecruitmentCandidateController::class, 'completeUserInterviewRound']);
                Route::post('recruitment/candidates/{candidate}/save-user-interview-round-evaluation', [HrRecruitmentCandidateController::class, 'saveUserInterviewRoundEvaluation']);
                Route::post('recruitment/candidates/{candidate}/upload-user-interview-round-summary', [HrRecruitmentCandidateController::class, 'uploadUserInterviewRoundSummary']);
                Route::post('recruitment/candidates/{candidate}/rounds/{round}/send-eval-wa/{evaluation}', [HrRecruitmentCandidateController::class, 'sendInterviewerEvaluationLink']);
                Route::post('recruitment/candidates/{candidate}/rounds/{round}/send-candidate-wa', [HrRecruitmentCandidateController::class, 'sendUserInterviewCandidateWa']);
                Route::get('recruitment/evaluations/{evaluation}/preview', [HrRecruitmentCandidateController::class, 'previewUserInterviewEvaluation']);
                Route::post('recruitment/candidates/{candidate}/send-reference-check-request', [HrRecruitmentCandidateController::class, 'sendReferenceCheckRequest']);
                Route::post('recruitment/candidates/{candidate}/send-reference-check-wa', [HrRecruitmentCandidateController::class, 'sendReferenceCheckWa']);
                Route::post('recruitment/candidates/{candidate}/upload-reference-check-summary', [HrRecruitmentCandidateController::class, 'uploadReferenceCheckSummary']);
                Route::get('recruitment/candidates/{candidate}/reference-check-summary-preview', [HrRecruitmentCandidateController::class, 'previewReferenceCheckSummary']);
                Route::get('recruitment/candidates/{candidate}/user-interview-round/{round}/summary-preview', [HrRecruitmentCandidateController::class, 'previewUserInterviewRoundSummary']);
                Route::get('recruitment/candidates/{candidate}/user-interview-round/{round}/evaluation-recap-preview', [HrRecruitmentCandidateController::class, 'previewUserInterviewEvaluationRecap']);
                Route::get('recruitment/candidates/{candidate}/pkb-approval-recap-preview', [HrRecruitmentCandidateController::class, 'previewPkbApprovalRecap']);
                Route::post('recruitment/candidates/{candidate}/send-offering-with-signature', [HrRecruitmentCandidateController::class, 'sendOfferingLetterWithSignature']);
                Route::post('recruitment/candidates/{candidate}/send-offering-wa', [HrRecruitmentCandidateController::class, 'sendOfferingLetterWa']);
                Route::post('recruitment/candidates/{candidate}/send-pkb-approval-request', [HrRecruitmentCandidateController::class, 'sendPkbApprovalRequest']);
                Route::post('recruitment/candidates/{candidate}/pkb-signers/{signer}/resend-wa', [HrRecruitmentCandidateController::class, 'resendPkbSignerWa']);
                Route::post('recruitment/candidates/{candidate}/send-onboarding-link', [HrRecruitmentCandidateController::class, 'sendOnboardingFormLink']);
                Route::post('recruitment/candidates/{candidate}/send-onboarding-wa', [HrRecruitmentCandidateController::class, 'sendOnboardingWa']);
                Route::post('recruitment/candidates/{candidate}/import-onboarding', [HrRecruitmentCandidateController::class, 'importCandidateOnboarding']);
                Route::post('recruitment/candidates/{candidate}/save-onboarding-draft', [HrRecruitmentCandidateController::class, 'saveCandidateOnboardingData']);
            });

            Route::middleware('frontend.menu:hr-recruitment-requests')->group(function () {
                Route::get('recruitment/requests', [HrRecruitmentRequestController::class, 'index']);
                Route::post('recruitment/requests/{recruitmentRequest}/decide', [HrRecruitmentRequestController::class, 'decide']);
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
            Route::post('/profile/gate-qr-usage', [StaffPortalController::class, 'storeGateQrUsage']);

            Route::middleware('frontend.menu:staff-contracts')->group(function () {
                Route::get('/contracts', [StaffPortalController::class, 'contracts']);
                Route::get('/contracts/{contractId}/pdf-preview', [StaffPortalController::class, 'previewContractPdf']);
            });

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

            Route::middleware('frontend.menu:staff-recruitment-requests')->group(function () {
                Route::get('/recruitment/requests', [StaffRecruitmentRequestController::class, 'index']);
                Route::post('/recruitment/requests', [StaffRecruitmentRequestController::class, 'store']);
            });

            Route::middleware('frontend.menu:staff-subordinate-candidates')->group(function () {
                Route::get('/subordinate-candidates', [StaffPortalController::class, 'subordinateCandidates']);
            });
        });
    });
});
