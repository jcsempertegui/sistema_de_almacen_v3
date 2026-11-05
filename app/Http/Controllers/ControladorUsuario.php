<?php

namespace App\Http\Controllers;

use App\Actions\Usuarios\ActualizarUsuario;
use App\Actions\Usuarios\CrearUsuario;
use App\Http\Requests\SolicitudBusqueda;
use App\Http\Requests\SolicitudEstadoUsuario;
use App\Http\Requests\SolicitudUsuario;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ControladorUsuario extends Controller
{
    public function index(SolicitudBusqueda $request): View
    {
        Gate::authorize('viewAny', Usuario::class);
        $filters = $request->validated();
        $query = Usuario::with('rol')->orderBy('nombre')->orderBy('id');
        $search = $filters['q'] ?? null;
        if ($search !== null && $search !== '') {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('nombre', 'like', $pattern)->orWhere('correo', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }

        return view('usuarios.index', ['usuarios' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Usuario::class);

        return view('usuarios.form', ['usuario' => new Usuario(['activo' => true]), 'roles' => $this->roles()]);
    }

    public function store(SolicitudUsuario $request, CrearUsuario $accion): RedirectResponse
    {
        try {
            $accion->handle($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['correo' => 'El correo electrónico ya está registrado.']);
        }

        return redirect()->route('usuarios.index')->with('estado', 'Usuario registrado.');
    }

    public function edit(Usuario $usuario): View
    {
        Gate::authorize('update', $usuario);

        return view('usuarios.form', ['usuario' => $usuario, 'roles' => $this->roles($usuario)]);
    }

    public function update(SolicitudUsuario $request, Usuario $usuario, ActualizarUsuario $accion): RedirectResponse
    {
        try {
            $accion->handle($request->user(), $usuario, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['correo' => 'El correo electrónico ya está registrado.']);
        }

        return redirect()->route('usuarios.index')->with('estado', 'Usuario actualizado. Si cambió el acceso, sus sesiones fueron revocadas.');
    }

    public function estado(SolicitudEstadoUsuario $request, Usuario $usuario, ActualizarUsuario $accion): RedirectResponse
    {
        $accion->handle($request->user(), $usuario, $request->validated());

        return redirect()->route('usuarios.index')->with('estado', 'Estado del usuario actualizado.');
    }

    private function roles(?Usuario $target = null): Collection
    {
        return Rol::where(fn ($query) => $query->where('activo', true)->orWhere('id', $target?->rol_id ?? 0))->orderBy('nombre')->get()->filter(fn (Rol $rol) => app(ModificacionSegura::class)->canManageRole(auth()->user(), $rol));
    }
}
