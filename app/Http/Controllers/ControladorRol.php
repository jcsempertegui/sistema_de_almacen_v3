<?php

namespace App\Http\Controllers;

use App\Actions\Roles\GuardarRol;
use App\Http\Requests\SolicitudBusqueda;
use App\Http\Requests\SolicitudEstadoRol;
use App\Http\Requests\SolicitudRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Support\CatalogoPermisos;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ControladorRol extends Controller
{
    public function index(SolicitudBusqueda $request): View
    {
        Gate::authorize('viewAny', Rol::class);
        $filters = $request->validated();
        $roles = Rol::withCount('usuarios')->with(['permisos' => fn ($query) => $query->where('permiso_rol.activo', true)])->orderBy('nombre')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $roles->where(fn ($query) => $query->where('nombre', 'like', $pattern)->orWhere('codigo', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $roles->where('activo', $estado === 'active');
        }

        return view('roles.index', ['roles' => $roles->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Rol::class);

        return $this->form(new Rol);
    }

    public function edit(Rol $rol): View
    {
        abort_unless(Gate::allows('update', $rol) || Gate::allows('assignPermissions', $rol) || Gate::allows('changeStatus', $rol), 403, 'No tienes permiso para modificar este rol.');

        return $this->form($rol);
    }

    private function form(Rol $rol): View
    {
        return view('roles.form', ['rol' => $rol, 'permisos' => Permiso::whereIn('codigo', array_keys(CatalogoPermisos::LABELS))->orderBy('codigo')->get()->groupBy(fn (Permiso $permiso) => explode('.', $permiso->codigo)[0])]);
    }

    public function store(SolicitudRol $request, GuardarRol $accion): RedirectResponse
    {
        return $this->save($request, null, $accion);
    }

    public function update(SolicitudRol $request, Rol $rol, GuardarRol $accion): RedirectResponse
    {
        return $this->save($request, $rol, $accion);
    }

    private function save(SolicitudRol $request, ?Rol $rol, GuardarRol $accion): RedirectResponse
    {
        try {
            $accion->handle($request->user(), $rol, $request->validated());
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => 'El código del rol ya está registrado.']);
        }

        return redirect()->route('roles.index')->with('estado', $rol ? 'Rol actualizado. Los cambios de acceso revocan las sesiones anteriores.' : 'Rol registrado.');
    }

    public function estado(SolicitudEstadoRol $request, Rol $rol, GuardarRol $accion): RedirectResponse
    {
        $accion->handle($request->user(), $rol, $request->validated());

        return redirect()->route('roles.index')->with('estado', 'Estado del rol actualizado. Sus sesiones anteriores fueron revocadas.');
    }
}
