<?php

use App\Http\Controllers\AdministrativeAuditController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::view('/panel', 'dashboard')->middleware(['auth', 'active', 'can:access-panel'])->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/usuarios/{user}/estado', [UserController::class, 'status'])->name('users.status');
});

require __DIR__.'/auth.php';
require __DIR__.'/catalog.php';
require __DIR__.'/inventory.php';
require __DIR__.'/worker-operations.php';

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('roles', RoleController::class)->names('roles')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/roles/{role}/estado', [RoleController::class, 'status'])->name('roles.status');
    Route::get('/auditoria', [AdministrativeAuditController::class, 'index'])->name('audit.index');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::resource('trabajadores', WorkerController::class)->parameters(['trabajadores' => 'worker'])->names('workers')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/trabajadores/{worker}/estado', [WorkerController::class, 'status'])->name('workers.status');
});
