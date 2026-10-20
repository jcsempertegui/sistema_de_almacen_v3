<?php

use App\Http\Controllers\Control\BackupController;
use App\Http\Controllers\Control\CorrectionController;
use App\Http\Controllers\Control\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/respaldos', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/respaldos', [BackupController::class, 'request'])->name('backups.request');
    Route::get('/respaldos/{backup}/descargar', [BackupController::class, 'download'])->whereNumber('backup')->name('backups.download');
    Route::get('/respaldos/{backup}/manifiesto', [BackupController::class, 'manifest'])->whereNumber('backup')->name('backups.manifest');
    Route::post('/respaldos/{backup}/verificar', [BackupController::class, 'verify'])->whereNumber('backup')->name('backups.verify');
    Route::post('/respaldos/{backup}/ensayo', [BackupController::class, 'rehearsal'])->whereNumber('backup')->name('backups.rehearsal');
    Route::post('/ensayos/{rehearsal}/autorizar', [BackupController::class, 'approve'])->whereNumber('rehearsal')->name('rehearsals.approve');
    Route::post('/ensayos/{rehearsal}/revocar', [BackupController::class, 'revoke'])->whereNumber('rehearsal')->name('rehearsals.revoke');
    Route::get('/reportes/{kind}', [ReportController::class, 'index'])->name('control-reports.index');
    Route::prefix('correcciones/{kind}')->where(['kind' => 'adjustment|compensation'])->name('corrections.')->group(function () {
        Route::get('/', [CorrectionController::class, 'index'])->name('index');
        Route::get('/crear', [CorrectionController::class, 'create'])->name('create');
        Route::post('/', [CorrectionController::class, 'store'])->name('store');
        Route::get('/{document}', [CorrectionController::class, 'show'])->whereNumber('document')->name('show');
        Route::get('/{document}/editar', [CorrectionController::class, 'edit'])->whereNumber('document')->name('edit');
        Route::put('/{document}', [CorrectionController::class, 'update'])->whereNumber('document')->name('update');
        Route::post('/{document}/{action}', [CorrectionController::class, 'transition'])->whereNumber('document')->where('action', 'request|approve|reject|revoke|cancel|execute')->name('transition');
    });
});
