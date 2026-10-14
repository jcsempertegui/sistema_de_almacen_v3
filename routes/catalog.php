<?php

use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\MeasurementUnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('categorias', CategoryController::class)->parameters(['categorias' => 'category'])->names('categories')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/categorias/{category}/estado', [CategoryController::class, 'status'])->name('categories.status');
    Route::resource('unidades-de-medida', MeasurementUnitController::class)->parameters(['unidades-de-medida' => 'measurement_unit'])->names('measurement-units')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/unidades-de-medida/{measurement_unit}/estado', [MeasurementUnitController::class, 'status'])->name('measurement-units.status');
});
