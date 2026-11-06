<?php

use App\Http\Controllers\Control\ControladorCorreccion;
use App\Http\Controllers\Control\ControladorReporte;
use App\Http\Controllers\Control\ControladorRespaldo;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/respaldos', [ControladorRespaldo::class, 'index'])->name('backups.index');
    Route::post('/respaldos', [ControladorRespaldo::class, 'request'])->name('backups.request');
    Route::get('/respaldos/{backup}/descargar', [ControladorRespaldo::class, 'download'])->whereNumber('backup')->name('backups.download');
    Route::get('/respaldos/{backup}/manifiesto', [ControladorRespaldo::class, 'manifiesto'])->whereNumber('backup')->name('backups.manifiesto');
    Route::post('/respaldos/{backup}/verificar', [ControladorRespaldo::class, 'verify'])->whereNumber('backup')->name('backups.verify');
    Route::post('/respaldos/{backup}/ensayo', [ControladorRespaldo::class, 'rehearsal'])->whereNumber('backup')->name('backups.rehearsal');
    Route::post('/ensayos/{rehearsal}/autorizar', [ControladorRespaldo::class, 'approve'])->whereNumber('rehearsal')->name('rehearsals.approve');
    Route::post('/ensayos/{rehearsal}/revocar', [ControladorRespaldo::class, 'revoke'])->whereNumber('rehearsal')->name('rehearsals.revoke');
    Route::get('/reportes/{clase}', [ControladorReporte::class, 'index'])->name('reportes-control.index');
    Route::prefix('correcciones/{clase}')->where(['clase' => 'ajustes|compensaciones|adjustment|compensation'])->name('corrections.')->group(function () {
        Route::get('/', [ControladorCorreccion::class, 'index'])->name('index');
        Route::get('/crear', [ControladorCorreccion::class, 'create'])->name('create');
        Route::post('/', [ControladorCorreccion::class, 'store'])->name('store');
        Route::get('/{documento}', [ControladorCorreccion::class, 'show'])->whereNumber('documento')->name('show');
        Route::get('/{documento}/editar', [ControladorCorreccion::class, 'edit'])->whereNumber('documento')->name('edit');
        Route::put('/{documento}', [ControladorCorreccion::class, 'update'])->whereNumber('documento')->name('update');
        Route::post('/{documento}/{accion}', [ControladorCorreccion::class, 'transition'])->whereNumber('documento')->where('accion', 'solicitar|autorizar|rechazar|revocar|cancelar|ejecutar|request|approve|reject|revoke|cancel|execute')->name('transition');
    });
});
