<?php

use App\Http\Middleware\RedirigirRutasAntiguas;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/estado-sistema',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RedirigirRutasAntiguas::class);
        $middleware->trimStrings(except: ['contrasena', 'contrasena_confirmation']);
        $middleware->alias(['active' => VerificarUsuarioActivo::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['contrasena', 'contrasena_confirmation']);
    })->create();
