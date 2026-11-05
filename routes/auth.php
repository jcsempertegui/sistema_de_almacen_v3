<?php

use App\Http\Controllers\Auth\ControladorSesionAutenticada;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [ControladorSesionAutenticada::class, 'create'])->name('login');
    Route::post('/login', [ControladorSesionAutenticada::class, 'store'])->name('login.store');
});
Route::post('/logout', [ControladorSesionAutenticada::class, 'destroy'])->middleware('auth')->name('logout');
