<?php

use App\Http\Controllers\Inventory\InitialStockImportController;
use App\Http\Controllers\Inventory\InventoryDocumentController;
use App\Http\Controllers\Inventory\InventoryQueryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/apertura/importar', [InitialStockImportController::class, 'create'])->name('initial-stock-import.create');
    Route::post('/apertura/importar/revisar', [InitialStockImportController::class, 'preview'])->name('initial-stock-import.preview');
    Route::post('/apertura/importar', [InitialStockImportController::class, 'store'])->name('initial-stock-import.store');
    Route::resource('remitos', InventoryDocumentController::class)->parameters(['remitos' => 'document'])->names('inventory-documents')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/remitos/{document}/confirmar', [InventoryDocumentController::class, 'confirm'])->name('inventory-documents.confirm');
    Route::post('/remitos/{document}/cancelar', [InventoryDocumentController::class, 'cancel'])->name('inventory-documents.cancel');
    Route::get('/existencias', [InventoryQueryController::class, 'stock'])->name('stock.index');
    Route::get('/movimientos', [InventoryQueryController::class, 'movements'])->name('movements.index');
});
