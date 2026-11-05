<?php

use App\Http\Controllers\Catalogo\ControladorAtributoCatalogo;
use App\Http\Controllers\Catalogo\ControladorAtributoCategoria;
use App\Http\Controllers\Catalogo\ControladorCategoria;
use App\Http\Controllers\Catalogo\ControladorProducto;
use App\Http\Controllers\Catalogo\ControladorUbicacion;
use App\Http\Controllers\Catalogo\ControladorUbicacionProducto;
use App\Http\Controllers\Catalogo\ControladorUnidadIndividual;
use App\Http\Controllers\Catalogo\ControladorUnidadMedida;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('unidades-individualizadas', ControladorUnidadIndividual::class)->parameters(['unidades-individualizadas' => 'unidad_individual'])->names('unidades-individuales')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/unidades-individualizadas/{unidad_individual}/estado', [ControladorUnidadIndividual::class, 'estado'])->name('unidades-individuales.estado');
    Route::post('/productos/revisar', [ControladorProducto::class, 'preview'])->name('productos.preview');
    Route::post('/productos/{producto}/revisar', [ControladorProducto::class, 'preview'])->name('productos.preview-update');
    Route::resource('productos', ControladorProducto::class)->parameters(['productos' => 'producto'])->names('productos')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/productos/{producto}/estado', [ControladorProducto::class, 'estado'])->name('productos.estado');
    Route::get('/productos/{producto}/ubicaciones', [ControladorUbicacionProducto::class, 'index'])->name('ubicaciones-producto.index');
    Route::get('/productos/{producto}/ubicaciones/create', [ControladorUbicacionProducto::class, 'create'])->name('ubicaciones-producto.create');
    Route::post('/productos/{producto}/ubicaciones', [ControladorUbicacionProducto::class, 'store'])->name('ubicaciones-producto.store');
    Route::patch('/productos/{producto}/ubicaciones/{ubicacion_producto}/estado', [ControladorUbicacionProducto::class, 'estado'])->name('ubicaciones-producto.estado')->scopeBindings();
    Route::get('/ubicaciones/arbol', [ControladorUbicacion::class, 'tree'])->name('ubicaciones.tree');
    Route::resource('ubicaciones', ControladorUbicacion::class)->parameters(['ubicaciones' => 'ubicacion'])->names('ubicaciones')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/ubicaciones/{ubicacion}/estado', [ControladorUbicacion::class, 'estado'])->name('ubicaciones.estado');
    Route::resource('atributos-tecnicos', ControladorAtributoCatalogo::class)->parameters(['atributos-tecnicos' => 'atributo_catalogo'])->names('atributos')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/atributos-tecnicos/{atributo_catalogo}/estado', [ControladorAtributoCatalogo::class, 'estado'])->name('atributos.estado');
    Route::scopeBindings()->group(function () {
        Route::resource('categorias.atributos', ControladorAtributoCategoria::class)->parameters(['categorias' => 'categoria', 'atributos' => 'atributo_categoria'])->names('atributos-categoria')->only(['index', 'create', 'store', 'edit', 'update']);
        Route::patch('/categorias/{categoria}/atributos/{atributo_categoria}/estado', [ControladorAtributoCategoria::class, 'estado'])->name('atributos-categoria.estado');
    });
    Route::resource('categorias', ControladorCategoria::class)->parameters(['categorias' => 'categoria'])->names('categorias')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/categorias/{categoria}/estado', [ControladorCategoria::class, 'estado'])->name('categorias.estado');
    Route::resource('unidades-de-medida', ControladorUnidadMedida::class)->parameters(['unidades-de-medida' => 'unidad_medida'])->names('unidades-medida')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/unidades-de-medida/{unidad_medida}/estado', [ControladorUnidadMedida::class, 'estado'])->name('unidades-medida.estado');
});
