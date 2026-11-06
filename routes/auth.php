<?php

use App\Http\Controllers\Auth\ControladorSesionAutenticada;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [ControladorSesionAutenticada::class, 'create'])->name('login');
    Route::post('/ingresar', [ControladorSesionAutenticada::class, 'store'])->name('login.store');
    Route::post('/login', [ControladorSesionAutenticada::class, 'store']);
});
Route::post('/cerrar-sesion', [ControladorSesionAutenticada::class, 'destroy'])->middleware('auth')->name('logout');
Route::post('/logout', [ControladorSesionAutenticada::class, 'destroy'])->middleware('auth');
