<?php

use App\Http\Controllers\Catalog\CatalogAttributeController;
use App\Http\Controllers\Catalog\CategoryAttributeController;
use App\Http\Controllers\Catalog\CategoryController;
use App\Http\Controllers\Catalog\IndividualUnitController;
use App\Http\Controllers\Catalog\LocationController;
use App\Http\Controllers\Catalog\MeasurementUnitController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ProductLocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('unidades-individualizadas', IndividualUnitController::class)->parameters(['unidades-individualizadas' => 'individual_unit'])->names('individual-units')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/unidades-individualizadas/{individual_unit}/estado', [IndividualUnitController::class, 'status'])->name('individual-units.status');
    Route::post('/productos/revisar', [ProductController::class, 'preview'])->name('products.preview');
    Route::post('/productos/{product}/revisar', [ProductController::class, 'preview'])->name('products.preview-update');
    Route::resource('productos', ProductController::class)->parameters(['productos' => 'product'])->names('products')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/productos/{product}/estado', [ProductController::class, 'status'])->name('products.status');
    Route::get('/productos/{product}/ubicaciones', [ProductLocationController::class, 'index'])->name('product-locations.index');
    Route::get('/productos/{product}/ubicaciones/create', [ProductLocationController::class, 'create'])->name('product-locations.create');
    Route::post('/productos/{product}/ubicaciones', [ProductLocationController::class, 'store'])->name('product-locations.store');
    Route::patch('/productos/{product}/ubicaciones/{product_location}/estado', [ProductLocationController::class, 'status'])->name('product-locations.status')->scopeBindings();
    Route::get('/ubicaciones/arbol', [LocationController::class, 'tree'])->name('locations.tree');
    Route::resource('ubicaciones', LocationController::class)->parameters(['ubicaciones' => 'location'])->names('locations')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/ubicaciones/{location}/estado', [LocationController::class, 'status'])->name('locations.status');
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
