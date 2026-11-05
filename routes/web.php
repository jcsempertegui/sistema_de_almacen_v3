<?php

use App\Http\Controllers\ControladorAuditoriaAdministrativa;
use App\Http\Controllers\ControladorPanel;
use App\Http\Controllers\ControladorRol;
use App\Http\Controllers\ControladorTrabajador;
use App\Http\Controllers\ControladorUsuario;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/panel', ControladorPanel::class)->middleware(['auth', 'active', 'can:access-panel'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('usuarios', ControladorUsuario::class)->parameters(['usuarios' => 'usuario'])->names('usuarios')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/usuarios/{usuario}/estado', [ControladorUsuario::class, 'estado'])->name('usuarios.estado');
});

require __DIR__.'/auth.php';
require __DIR__.'/catalogo.php';
require __DIR__.'/inventario.php';
require __DIR__.'/operaciones-trabajadores.php';
require __DIR__.'/control.php';

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('roles', ControladorRol::class)->parameters(['roles' => 'rol'])->names('roles')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/roles/{rol}/estado', [ControladorRol::class, 'estado'])->name('roles.estado');
    Route::get('/auditoria', [ControladorAuditoriaAdministrativa::class, 'index'])->name('audit.index');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('trabajadores', ControladorTrabajador::class)->parameters(['trabajadores' => 'trabajador'])->names('trabajadores')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/trabajadores/{trabajador}/estado', [ControladorTrabajador::class, 'estado'])->name('trabajadores.estado');
});
