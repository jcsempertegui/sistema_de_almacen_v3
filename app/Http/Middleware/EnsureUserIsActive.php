<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ? User::with('role')->find($request->user()->id) : null;
        if (! $user?->is_active || ! $user->role?->is_active
            || $request->session()->get('auth_version') !== $user->session_version
            || $request->session()->get('auth_role_id') !== $user->role_id
            || $request->session()->get('auth_role_version') !== $user->role->permissions_version) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'La sesión ya no es válida.'], 401);
            }

            return redirect()->route('login')->withErrors(['email' => 'La sesión ya no es válida. Inicia sesión nuevamente.']);
        }

        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
