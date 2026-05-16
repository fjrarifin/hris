<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PublicApprovalController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Staff\{
    PermissionController,
    OvertimeController,
    LeaveRequestController,
    AtkRequestController,
    PenilaianController,
    SelfAssessmentController,
    LeaveApprovalController,
    ProfileController,
    PublicHolidayController,
    DashboardController as StaffDashboardController
};
use App\Http\Controllers\GA\DashboardController as GADashboardController;
use App\Http\Controllers\HR\DashboardController;
use App\Http\Controllers\HR\Monitoring360Controller;
use App\Http\Controllers\HR\MonitoringSelfAssessmentController;
use App\Http\Controllers\HR\RelasiMasterController;
use App\Http\Controllers\HR\KaryawanController;
use App\Http\Controllers\HR\LeaveController;
use App\Http\Controllers\HR\ApprovalController;
use App\Http\Controllers\MGR\LeaveRequestController as MGRLeaveRequestController;
use App\Http\Controllers\WhatsAppController;
use App\Http\Controllers\PasswordController;
use Mockery\Generator\StringManipulation\Pass\Pass;
use App\Http\Controllers\PayrollController;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin'])
    ->middleware('guest')
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('guest');

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');


/*
|--------------------------------------------------------------------------
| Approval Public
|--------------------------------------------------------------------------
*/
Route::prefix('approval')->group(function () {
    Route::get('{token}', [PublicApprovalController::class, 'show'])
        ->name('approval.show');

    Route::post('{token}/approve', [PublicApprovalController::class, 'approve'])
        ->name('approval.approve');

    Route::post('{token}/reject', [PublicApprovalController::class, 'reject'])
        ->name('approval.reject');
});


/*
|--------------------------------------------------------------------------
| ROOT
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| DASHBOARD GATE (SATU PINTU)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'force.password'])->group(function () {

    Route::get('/dashboard', function () {
        return match ((int) Auth::user()->level) {
            0 => redirect()->route('it.dashboard'),
            1 => redirect()->route('admin.dashboard'),
            2 => redirect()->route('hr.dashboard'),
            default => redirect()->route('staff.dashboard'),
        };
    })->name('dashboard');

    // SEMUA route protected lainnya
});

Route::middleware('auth')->group(function () {

    Route::get('/change-password', [PasswordController::class, 'change'])
        ->name('password.change');

    Route::post('/change-password', [PasswordController::class, 'update'])
        ->name('password.update');
});



Route::prefix('notifications')->middleware('auth')->group(function () {
    Route::post('/{id}/read', function ($id) {
        $notif = Auth::user()->notifications()->findOrFail($id);
        $notif->markAsRead();
        return response()->json(['success' => true]);
    })->name('notifications.read');

    Route::post('/read-all', function () {
        Auth::user()->unreadNotifications->markAsRead();
        return response()->json(['success' => true]);
    })->name('notifications.readAll');
});


/*
|--------------------------------------------------------------------------
| PROFILE (SEMUA ROLE)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::middleware(['auth', 'force.password'])->group(function () {

    Route::get('/dashboard', function () {
        return match ((int) Auth::user()->level) {
            0 => redirect()->route('it.dashboard'),
            1 => redirect()->route('admin.dashboard'),
            2 => redirect()->route('hr.dashboard'),
            default => redirect()->route('staff.dashboard'),
        };
    })->name('dashboard');

    // SEMUA route protected lainnya
});


// Forget Password Routes
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordController::class, 'showForgotForm'])->name('password.forgot');
    Route::post('/forgot-password/send-otp', [PasswordController::class, 'sendOtp'])->name('password.send-otp');
    Route::get('/verify-otp', [PasswordController::class, 'showVerifyOtpForm'])->name('password.verify-otp');
    Route::post('/verify-otp', [PasswordController::class, 'verifyOtp'])->name('password.verify-otp-post');
    Route::get('/reset-password', [PasswordController::class, 'showResetForm'])->name('password.reset-form');
    Route::post('/reset-password', [PasswordController::class, 'resetPassword'])->name('password.reset');
    Route::post('/resend-otp', [PasswordController::class, 'resendOtp'])->name('password.resend-otp');
});

/*
|--------------------------------------------------------------------------
| IT (LEVEL 0)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'level:0'])
    ->prefix('it')
    ->name('it.')
    ->group(function () {

        Route::get('/dashboard', fn() => view('it.dashboard'))
            ->name('dashboard');
    });


/*
|--------------------------------------------------------------------------
| ADMIN (LEVEL 1)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'level:1'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', fn() => view('admin.dashboard'))
            ->name('dashboard');

        Route::resource('menus', MenuController::class);
    });


/*
|--------------------------------------------------------------------------
| HR (LEVEL 2)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'level:2'])
    ->prefix('hr')
    ->name('hr.')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/360', [Monitoring360Controller::class, 'index'])->name('360.index');
        Route::get('/sa', [MonitoringSelfAssessmentController::class, 'index'])
            ->name('sa.index');

        Route::get('/sa/detail/{nik}', [MonitoringSelfAssessmentController::class, 'detail'])
            ->name('sa.detail');

        Route::get('/sa/export', [MonitoringSelfAssessmentController::class, 'export'])
            ->name('sa.export');

        Route::get('/relasi', [RelasiMasterController::class, 'index'])
            ->name('360.relasi');

        Route::get('/relasi/{nik}', [RelasiMasterController::class, 'detail'])
            ->name('360.relasi.detail');

        Route::post('/relasi/{nik}', [RelasiMasterController::class, 'store'])
            ->name('360.relasi.store');

        Route::delete('/relasi/{nik}', [RelasiMasterController::class, 'destroy'])
            ->name('360.relasi.destroy');

        Route::get('/karyawan', [KaryawanController::class, 'index'])
            ->name('karyawan.index');

        Route::get('/karyawan/{nik}', [KaryawanController::class, 'edit'])
            ->name('karyawan.detail');

        Route::post('/karyawan/{nik}', [KaryawanController::class, 'update'])
            ->name('karyawan.update');

        Route::get('/leave', [LeaveController::class, 'index'])
            ->name('leave.index');
        Route::post('/leave/{id}/approve', [LeaveController::class, 'approve'])
            ->name('leave.approve');
        Route::post('/leave/{id}/reject', [LeaveController::class, 'reject'])
            ->name('leave.reject');
        Route::post('/leave/{id}/cancel', [LeaveController::class, 'cancel'])
            ->name('leave.cancel');

        Route::prefix('approval')->group(function () {
            Route::get('{type}', [ApprovalController::class, 'index'])->name('approval.index');
            Route::get('{type}/export', [ApprovalController::class, 'export'])->name('approval.export');
            Route::post('{type}/{id}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
            Route::post('{type}/{id}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
        });

        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::get('/payroll/debug/{id}', [PayrollController::class, 'debug'])->name('payroll.debug');
        Route::get('/payroll/template', [PayrollController::class, 'downloadTemplate'])->name('payroll.template');
        Route::get('/payroll/upload', [PayrollController::class, 'form'])->name('payroll.upload.form');
        Route::post('/payroll/upload', [PayrollController::class, 'upload'])->name('payroll.upload');
        Route::post('/payroll/blast-email', [PayrollController::class, 'blastEmail'])->name('payroll.blast-email');
        Route::post('/payroll/sync', [PayrollController::class, 'syncKaryawanFromGsheet'])->name('payroll.sync');
        Route::post('/payroll/sync-raw', [PayrollController::class, 'syncRawPayroll'])->name('payroll.sync-raw');
        Route::post('/payroll/convert', [PayrollController::class, 'convertPayroll'])->name('payroll.convert');
        Route::get('/payroll/{id}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::get('/payroll/{id}/download', [PayrollController::class, 'download'])->name('payroll.download');
        Route::post('/payroll/{id}/send-email', [PayrollController::class, 'sendEmail'])->name('payroll.send-email');
    });

Route::prefix('hr/monitoring/360')->middleware('auth')->group(function () {
    Route::get('/modal-submit', [Monitoring360Controller::class, 'modalSudahSubmit']);
    Route::get('/modal-belum-submit', [Monitoring360Controller::class, 'modalBelumSubmit']);
});


/*
|--------------------------------------------------------------------------
| STAFF (LEVEL 3)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'level:3'])
    ->prefix('staff')
    ->name('staff.')
    ->group(function () {

        Route::get('/dashboard', [StaffDashboardController::class, 'index'])->name('dashboard');

        Route::get('/cuti/pengajuan', [LeaveRequestController::class, 'index'])->name('leave.index');
        Route::post('/cuti/pengajuan', [LeaveRequestController::class, 'store'])->name('leave.store');
        Route::delete('/cuti/pengajuan/{id}', [LeaveRequestController::class, 'destroy'])->name('leave.destroy');

        Route::get('/atk/pengajuan', [AtkRequestController::class, 'index'])->name('atk.index');
        Route::post('/atk/pengajuan', [AtkRequestController::class, 'store'])->name('atk.store');
        Route::delete('/atk/pengajuan/{id}', [AtkRequestController::class, 'destroy'])->name('atk.destroy');

        Route::get('/izin-sakit', [PermissionController::class, 'index'])->name('permission.index');
        Route::post('/izin-sakit', [PermissionController::class, 'store'])->name('permission.store');
        Route::delete('/izin-sakit/{id}', [PermissionController::class, 'destroy'])->name('permission.destroy');

        Route::get('/lembur', [OvertimeController::class, 'index'])->name('overtime.index');
        Route::post('/lembur', [OvertimeController::class, 'store'])->name('overtime.store');
        Route::delete('/lembur/{id}', [OvertimeController::class, 'destroy'])->name('overtime.destroy');

        Route::get('/performance', [PenilaianController::class, 'index'])->name('performance.index');
        Route::post('/performance', [PenilaianController::class, 'store'])->name('performance.store');

        Route::get('/self-assessment', [SelfAssessmentController::class, 'index'])->name('self-assessment.index');
        Route::post('/self-assessment', [SelfAssessmentController::class, 'store'])->name('self-assessment.store');

        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

        Route::prefix('approval')
            ->name('approval.')
            ->middleware(['auth'])
            ->group(function () {
                Route::get('/leave', [LeaveApprovalController::class, 'index'])
                    ->name('leave.index');

                Route::post('/leave/{id}/approve', [LeaveApprovalController::class, 'approve'])
                    ->name('leave.approve');

                Route::post('/leave/{id}/reject', [LeaveApprovalController::class, 'reject'])
                    ->name('leave.reject');

                // Public Holiday Approval Routes
                Route::post('/ph/{id}/approve', [LeaveApprovalController::class, 'approvePH'])
                    ->name('ph.approve');

                Route::post('/ph/{id}/reject', [LeaveApprovalController::class, 'rejectPH'])
                    ->name('ph.reject');
            });

        Route::prefix('public-holiday')->name('public-holiday.')->group(function () {
            Route::get('/', [PublicHolidayController::class, 'index'])->name('index');
            Route::post('/', [PublicHolidayController::class, 'store'])->name('store');
            Route::delete('/{id}', [PublicHolidayController::class, 'destroy'])->name('destroy');
        });
    });


/*
|--------------------------------------------------------------------------
| MGR (LEVEL 3 - Second Level Approval)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'level:3'])
    ->prefix('mgr')
    ->name('mgr.')
    ->group(function () {

        Route::prefix('approval')
            ->name('approval.')
            ->middleware(['auth'])
            ->group(function () {
                Route::get('/leave', [MGRLeaveRequestController::class, 'index'])
                    ->name('leave.index');

                Route::post('/leave/{id}/approve', [MGRLeaveRequestController::class, 'approve'])
                    ->name('leave.approve');

                Route::post('/leave/{id}/reject', [MGRLeaveRequestController::class, 'reject'])
                    ->name('leave.reject');
            });
    });


/*
|--------------------------------------------------------------------------
| TEST
|--------------------------------------------------------------------------
*/
Route::get('/wa-test', [WhatsAppController::class, 'test']);
