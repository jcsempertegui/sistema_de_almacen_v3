<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SolicitudIngreso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ControladorSesionAutenticada extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(SolicitudIngreso $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->put('auth_version', $request->user()->version_sesion);
        $request->session()->put('auth_role_id', $request->user()->rol_id);
        $request->session()->put('auth_role_version', $request->user()->rol->version_permisos);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
