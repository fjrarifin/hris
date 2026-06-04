<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HR\Monitoring360Controller;
use App\Http\Controllers\HR\MonitoringSelfAssessmentController;
use App\Http\Controllers\HR\RelasiMasterController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PublicApprovalController;
use App\Http\Controllers\Staff\AtkRequestController;
use App\Http\Controllers\Staff\PenilaianController;
use App\Http\Controllers\Staff\SelfAssessmentController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('approval')->group(function () {
    Route::get('{token}', [PublicApprovalController::class, 'show'])
        ->name('approval.show');

    Route::post('{token}/approve', [PublicApprovalController::class, 'approve'])
        ->name('approval.approve');

    Route::post('{token}/reject', [PublicApprovalController::class, 'reject'])
        ->name('approval.reject');
});

Route::get('/', fn () => redirect()->away(config('services.frontend.base_url')));
Route::get('/dashboard', fn () => redirect()->away(config('services.frontend.base_url')))
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/change-password', [PasswordController::class, 'change'])
        ->name('password.change');
    Route::post('/change-password', [PasswordController::class, 'update'])
        ->name('password.update');
});

Route::prefix('notifications')->middleware('auth')->group(function () {
    Route::post('/{id}/read', function ($id) {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    })->name('notifications.read');

    Route::post('/read-all', function () {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    })->name('notifications.readAll');
});

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordController::class, 'showForgotForm'])->name('password.forgot');
    Route::post('/forgot-password/send-otp', [PasswordController::class, 'sendOtp'])->name('password.send-otp');
    Route::get('/verify-otp', [PasswordController::class, 'showVerifyOtpForm'])->name('password.verify-otp');
    Route::post('/verify-otp', [PasswordController::class, 'verifyOtp'])->name('password.verify-otp-post');
    Route::get('/reset-password', [PasswordController::class, 'showResetForm'])->name('password.reset-form');
    Route::post('/reset-password', [PasswordController::class, 'resetPassword'])->name('password.reset');
    Route::post('/resend-otp', [PasswordController::class, 'resendOtp'])->name('password.resend-otp');
});

Route::middleware(['auth', 'payroll.access'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/debug/{id}', [PayrollController::class, 'debug'])->name('payroll.debug');
        Route::get('/payroll/template', [PayrollController::class, 'downloadTemplate'])->name('payroll.template');
        Route::get('/payroll/history', [PayrollController::class, 'history'])->name('payroll.history');
        Route::get('/payroll/email-template', [PayrollController::class, 'emailTemplate'])->name('payroll.email-template');
        Route::post('/payroll/email-template', [PayrollController::class, 'updateEmailTemplate'])->name('payroll.email-template.update');
        Route::get('/payroll/export', [PayrollController::class, 'export'])->name('payroll.export');
        Route::get('/payroll/upload', [PayrollController::class, 'form'])->name('payroll.upload.form');
        Route::post('/payroll/upload', [PayrollController::class, 'upload'])->name('payroll.upload');
        Route::post('/payroll/blast-email', [PayrollController::class, 'blastEmail'])->name('payroll.blast-email');
        Route::post('/payroll/sync', [PayrollController::class, 'syncKaryawanFromGsheet'])->name('payroll.sync');
        Route::post('/payroll/sync-raw', [PayrollController::class, 'syncRawPayroll'])->name('payroll.sync-raw');
        Route::post('/payroll/convert', [PayrollController::class, 'convertPayroll'])->name('payroll.convert');
        Route::post('/payroll/{id}/validate', [PayrollController::class, 'validatePayroll'])->name('payroll.validate');
        Route::post('/payroll/{id}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');
        Route::post('/payroll/{id}/reject', [PayrollController::class, 'reject'])->name('payroll.reject');
        Route::post('/payroll/{id}/lock', [PayrollController::class, 'lock'])->name('payroll.lock');
        Route::post('/payroll/{id}/unlock', [PayrollController::class, 'unlock'])->name('payroll.unlock');
        Route::get('/payroll/{id}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('/payroll/{id}/download', [PayrollController::class, 'download'])->name('payroll.download');
        Route::post('/payroll/{id}/send-email', [PayrollController::class, 'sendEmail'])->name('payroll.send-email');
    });

Route::middleware(['auth', 'level:2'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {
        Route::get('/360', [Monitoring360Controller::class, 'index'])->middleware('hr.full')->name('360.index');
        Route::get('/sa', [MonitoringSelfAssessmentController::class, 'index'])->middleware('hr.full')->name('sa.index');
        Route::get('/sa/detail/{nik}', [MonitoringSelfAssessmentController::class, 'detail'])->middleware('hr.full')->name('sa.detail');
        Route::get('/sa/export', [MonitoringSelfAssessmentController::class, 'export'])->middleware('hr.full')->name('sa.export');
        Route::get('/relasi', [RelasiMasterController::class, 'index'])->middleware('hr.full')->name('360.relasi');
        Route::get('/relasi/{nik}', [RelasiMasterController::class, 'detail'])->middleware('hr.full')->name('360.relasi.detail');
        Route::post('/relasi/{nik}', [RelasiMasterController::class, 'store'])->middleware('hr.full')->name('360.relasi.store');
        Route::delete('/relasi/{nik}', [RelasiMasterController::class, 'destroy'])->middleware('hr.full')->name('360.relasi.destroy');
    });

Route::prefix('hr/monitoring/360')->middleware('auth')->group(function () {
    Route::get('/modal-submit', [Monitoring360Controller::class, 'modalSudahSubmit']);
    Route::get('/modal-belum-submit', [Monitoring360Controller::class, 'modalBelumSubmit']);
});

Route::middleware(['auth', 'level:3'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {
        Route::get('/atk/pengajuan', [AtkRequestController::class, 'index'])->name('atk.index');
        Route::post('/atk/pengajuan', [AtkRequestController::class, 'store'])->name('atk.store');
        Route::delete('/atk/pengajuan/{id}', [AtkRequestController::class, 'destroy'])->name('atk.destroy');
        Route::get('/performance', [PenilaianController::class, 'index'])->name('performance.index');
        Route::post('/performance', [PenilaianController::class, 'store'])->name('performance.store');
        Route::get('/self-assessment', [SelfAssessmentController::class, 'index'])->name('self-assessment.index');
        Route::post('/self-assessment', [SelfAssessmentController::class, 'store'])->name('self-assessment.store');
    });
