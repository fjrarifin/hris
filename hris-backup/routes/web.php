<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\FaktorController;
use App\Http\Controllers\Admin\FaktorScoreController;
use App\Http\Controllers\Admin\KaryawanController;
use App\Http\Controllers\Admin\KaryawanKontrakController;
use App\Http\Controllers\Admin\MonitoringPenilaianController;
use App\Http\Controllers\Admin\RelasiMasterController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\SelfAssessment\SelfAssessmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\PenilaianController;
use App\Http\Controllers\AtkRequestController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('user.dashboard')
        : redirect()->route('login');
});

// guest
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
});

// auth
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/ubah-password', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('/ubah-password', [ChangePasswordController::class, 'update'])->name('password.update');

    // ✅ menu penilaian tetap boleh diakses setelah login
    Route::get('/penilaian', [PenilaianController::class, 'index'])->name('penilaian.index');
    Route::post('/penilaian', [PenilaianController::class, 'store'])->name('penilaian.store');

    Route::post('/self-assessment', [SelfAssessmentController::class, 'store'])->name('self.store');

    Route::get('/atk', [AtkRequestController::class, 'index'])->name('atk.index');
    Route::post('/atk', [AtkRequestController::class, 'store'])->name('atk.store');
    Route::delete('/atk/{id}', [AtkRequestController::class, 'destroy'])->name('atk.destroy');

    // ✅ ADMIN route group
    Route::prefix('admin')
        ->name('admin.')
        ->middleware('is.admin') // kita buat middleware ini
        ->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

            Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/faktor', [FaktorController::class, 'index'])->name('faktor.index');

            Route::get('/monitoring/penilaian', [MonitoringPenilaianController::class, 'index'])
                ->name('monitoring.index');

            Route::get('/monitoring/penilaian/{nik}', [MonitoringPenilaianController::class, 'detail'])
                ->name('monitoring.detail');

            Route::get('/faktor-score', [FaktorScoreController::class, 'index'])
                ->name('faktor-score.index');

            Route::get('/faktor-score/level/{levelId}', [FaktorScoreController::class, 'level'])
                ->name('faktor-score.level');

            Route::post('/faktor-score/level/{levelId}/generate', [FaktorScoreController::class, 'generateDefault'])
                ->name('faktor-score.generate');

            Route::get('/faktor-score/level/{levelId}/faktor/{faktorId}', [FaktorScoreController::class, 'edit'])
                ->name('faktor-score.edit');

            Route::post('/faktor-score/level/{levelId}/faktor/{faktorId}', [FaktorScoreController::class, 'update'])
                ->name('faktor-score.update');

            Route::get('/relasi-master', [RelasiMasterController::class, 'index'])->name('relasi-master.index');
            Route::get('/relasi-master/{nik}', [RelasiMasterController::class, 'detail'])->name('relasi-master.detail');
            Route::post('/relasi-master/{nik}/store', [RelasiMasterController::class, 'store'])->name('relasi-master.store');
            Route::delete('/relasi-master/{nik}/delete', [RelasiMasterController::class, 'destroy'])->name('relasi-master.destroy');

            Route::get('/karyawan', [KaryawanController::class, 'index'])->name('karyawan.index');
            Route::get('/karyawan/create', [KaryawanController::class, 'create'])->name('karyawan.create');
            Route::post('/karyawan', [KaryawanController::class, 'store'])->name('karyawan.store');
            Route::get('/karyawan/{nik}/edit', [KaryawanController::class, 'edit'])->name('karyawan.edit');
            Route::put('/karyawan/{nik}', [KaryawanController::class, 'update'])->name('karyawan.update');
            Route::delete('/karyawan/{nik}', [KaryawanController::class, 'destroy'])->name('karyawan.destroy');

            Route::post('/karyawan/{nik}/kontrak', [KaryawanKontrakController::class, 'store'])->name('karyawan.kontrak.store');
            Route::delete('/karyawan/{nik}/kontrak/{id}', [KaryawanKontrakController::class, 'destroy'])->name('karyawan.kontrak.destroy');
            Route::post('/karyawan/{nik}/kontrak/{id}/selesai', [KaryawanKontrakController::class, 'finish'])->name('karyawan.kontrak.finish');

            Route::get('/monitoring/submit-review', [MonitoringPenilaianController::class, 'submitReview'])->name('monitoring.submit-review');

            Route::get('/admin/monitoring/penilaian/export', [MonitoringPenilaianController::class, 'export'])
                ->name('monitoring.export');

            Route::prefix('ga')
                ->name('ga.')
                ->group(function () {
                    Route::get('/asset', function () {
                        return view('admin.ga.asset.index');
                    })->name('asset.index');
                    Route::get('/budget', function () {
                        return view('admin.ga.asset.index');
                    })->name('asset.index');
                    Route::get('/compliance', function () {
                        return view('admin.ga.compliance.index');
                    })->name('compliance.index');
                    Route::get('/dashboard', function () {
                        return view('admin.ga.dashboard.index');
                    })->name('dashboard.index');
                    Route::get('/incident', function () {
                        return view('admin.ga.incident.index');
                    })->name('incident.index');
                    Route::get('/vendor', function () {
                        return view('admin.ga.vendor.index');
                    })->name('vendor.index');
                    Route::get('/maintenance', function () {
                        return view('admin.ga.maintenance.index');
                    })->name('maintenance.index');
                    Route::get('/budget', function () {
                        return view('admin.ga.budget.index');
                    })->name('budget.index');
                    Route::get('/sla', function () {
                        return view('admin.ga.sla.index');
                    })->name('sla.index');
                });
        });
});

// ✅ dashboard wajib lewat middleware must.change.password
Route::middleware(['auth', 'must.change.password'])->group(function () {
    Route::get('/dashboard', fn() => view('user.dashboard'))->name('user.dashboard');
});
