<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->is_active || $request->session()->get('auth_version') !== $user->session_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'La sesión ya no es válida.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'La sesión ya no es válida. Inicia sesión nuevamente.']);
        }

        return $next($request);
    }
}
