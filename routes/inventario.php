<?php

use App\Http\Controllers\Inventario\ControladorConsultaInventario;
use App\Http\Controllers\Inventario\ControladorDocumentoInventario;
use App\Http\Controllers\Inventario\ControladorImportacionExistenciasIniciales;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/apertura/importar', [ControladorImportacionExistenciasIniciales::class, 'create'])->name('importacion-existencias-iniciales.create');
    Route::post('/apertura/importar/revisar', [ControladorImportacionExistenciasIniciales::class, 'preview'])->name('importacion-existencias-iniciales.preview');
    Route::post('/apertura/importar', [ControladorImportacionExistenciasIniciales::class, 'store'])->name('importacion-existencias-iniciales.store');
    Route::resource('remitos', ControladorDocumentoInventario::class)->parameters(['remitos' => 'documento'])->names('documentos-inventario')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/remitos/{documento}/confirmar', [ControladorDocumentoInventario::class, 'confirm'])->name('documentos-inventario.confirm');
    Route::post('/remitos/{documento}/cancelar', [ControladorDocumentoInventario::class, 'cancel'])->name('documentos-inventario.cancel');
    Route::get('/existencias', [ControladorConsultaInventario::class, 'stock'])->name('stock.index');
    Route::get('/movimientos', [ControladorConsultaInventario::class, 'movements'])->name('movements.index');
});
