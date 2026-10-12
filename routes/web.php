<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::view('/panel', 'dashboard')->middleware(['auth', 'active', 'can:access-panel'])->name('dashboard');

Route::middleware(['auth', 'active', 'can:viewAny,App\\Models\\User'])->group(function () {
    Route::resource('usuarios', UserController::class)->parameters(['usuarios' => 'user'])->names('users')->only(['index', 'create', 'store', 'edit', 'update']);
    Route::patch('/usuarios/{user}/estado', [UserController::class, 'status'])->name('users.status');
});

require __DIR__.'/auth.php';

Route::middleware(['auth', 'active', 'can:viewAny,App\\Models\\Worker'])->group(function () {
    Route::resource('trabajadores', WorkerController::class)->parameters(['trabajadores' => 'worker'])->names('workers')->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::patch('/trabajadores/{worker}/estado', [WorkerController::class, 'status'])->name('workers.status');
});
