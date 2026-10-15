<?php

use App\Http\Controllers\Catalog\CatalogAttributeController;
use App\Http\Controllers\Catalog\CategoryAttributeController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\MeasurementUnitController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('atributos-tecnicos', CatalogAttributeController::class)->parameters(['atributos-tecnicos' => 'catalog_attribute'])->names('attributes')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/atributos-tecnicos/{catalog_attribute}/estado', [CatalogAttributeController::class, 'status'])->name('attributes.status');
    Route::scopeBindings()->group(function () {
        Route::resource('categorias.atributos', CategoryAttributeController::class)->parameters(['categorias' => 'category', 'atributos' => 'category_attribute'])->names('category-attributes')->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('/categorias/{category}/atributos/{category_attribute}/estado', [CategoryAttributeController::class, 'status'])->name('category-attributes.status');
    });
    Route::resource('categorias', CategoryController::class)->parameters(['categorias' => 'category'])->names('categories')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/categorias/{category}/estado', [CategoryController::class, 'status'])->name('categories.status');
    Route::resource('unidades-de-medida', MeasurementUnitController::class)->parameters(['unidades-de-medida' => 'measurement_unit'])->names('measurement-units')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/unidades-de-medida/{measurement_unit}/estado', [MeasurementUnitController::class, 'status'])->name('measurement-units.status');
});
