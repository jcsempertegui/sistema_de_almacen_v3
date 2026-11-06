<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user() ? Usuario::with('rol')->find($request->user()->id) : null;
        if (! $usuario?->activo || ! $usuario->rol?->activo
            || $request->session()->get('auth_version') !== $usuario->version_sesion
            || $request->session()->get('auth_role_id') !== $usuario->rol_id
            || $request->session()->get('auth_role_version') !== $usuario->rol->version_permisos) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'La sesión ya no es válida.'], 401);
            }

            return redirect()->route('login')->withErrors(['correo' => 'La sesión ya no es válida. Inicia sesión nuevamente.']);
        }

        Auth::guard('web')->setUser($usuario);

        if (in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $usuario->rol->duranteConsulta(fn () => $next($request));
        }

        return $next($request);
    }
}
