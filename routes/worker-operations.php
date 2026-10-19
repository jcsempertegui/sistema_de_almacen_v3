<?php

use App\Http\Controllers\WorkerOperations\WorkerOperationController as Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/disponibilidad', [Controller::class, 'availability'])->name('availability.index');
    Route::post('/devoluciones/items/{item}/regularizacion', [Controller::class, 'requestRegularization'])->name('return-regularizations.request');
    Route::post('/devoluciones/regularizaciones/{authorization}/{decision}', [Controller::class, 'decideRegularization'])->where('decision', 'approve|revoke')->name('return-regularizations.decide');
    Route::prefix('/operaciones/{kind}')->where(['kind' => 'deliveries|loans|returns'])->name('worker-operations.')->group(function () {
        Route::get('/', [Controller::class, 'index'])->name('index');
        Route::get('/crear', [Controller::class, 'create'])->name('create');
        Route::post('/', [Controller::class, 'store'])->name('store');
        Route::get('/{document}', [Controller::class, 'show'])->whereNumber('document')->name('show');
        Route::get('/{document}/editar', [Controller::class, 'edit'])->whereNumber('document')->name('edit');
        Route::put('/{document}', [Controller::class, 'update'])->whereNumber('document')->name('update');
        Route::post('/{document}/confirmar', [Controller::class, 'confirm'])->whereNumber('document')->name('confirm');
        Route::post('/{document}/cancelar', [Controller::class, 'cancel'])->whereNumber('document')->name('cancel');
    });
});
