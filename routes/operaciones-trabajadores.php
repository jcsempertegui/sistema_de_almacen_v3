<?php

use App\Http\Controllers\OperacionesTrabajadores\ControladorOperacionTrabajador as Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/disponibilidad', [Controller::class, 'availability'])->name('availability.index');
    Route::post('/devoluciones/items/{item}/regularizacion', [Controller::class, 'requestRegularization'])->name('regularizaciones-devolucion.request');
    Route::post('/devoluciones/regularizaciones/{authorization}/{decision}', [Controller::class, 'decideRegularization'])->where('decision', 'autorizar|revocar|approve|revoke')->name('regularizaciones-devolucion.decide');
    Route::prefix('/operaciones/{clase}')->where(['clase' => 'entregas|prestamos|devoluciones|deliveries|loans|returns'])->name('operaciones-trabajadores.')->group(function () {
        Route::get('/', [Controller::class, 'index'])->name('index');
        Route::get('/crear', [Controller::class, 'create'])->name('create');
        Route::post('/', [Controller::class, 'store'])->name('store');
        Route::get('/{documento}', [Controller::class, 'show'])->whereNumber('documento')->name('show');
        Route::get('/{documento}/editar', [Controller::class, 'edit'])->whereNumber('documento')->name('edit');
        Route::put('/{documento}', [Controller::class, 'update'])->whereNumber('documento')->name('update');
        Route::post('/{documento}/confirmar', [Controller::class, 'confirm'])->whereNumber('documento')->name('confirm');
        Route::post('/{documento}/cancelar', [Controller::class, 'cancel'])->whereNumber('documento')->name('cancel');
    });
});
