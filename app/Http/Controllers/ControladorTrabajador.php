<?php

namespace App\Http\Controllers;

use App\Actions\Trabajadores\GuardarTrabajador;
use App\Http\Requests\SolicitudBusqueda;
use App\Http\Requests\SolicitudEstadoTrabajador;
use App\Http\Requests\SolicitudTrabajador;
use App\Models\Trabajador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorTrabajador extends Controller
{
    public function index(SolicitudBusqueda $request): View
    {
        Gate::authorize('viewAny', Trabajador::class);
        $filters = $request->validated();
        $query = Trabajador::orderBy('apellidos')->orderBy('nombres')->orderBy('id');
        $search = $filters['q'] ?? null;
        if ($search !== null && $search !== '') {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($query) use ($pattern) {
                foreach (['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area'] as $field) {
                    $query->orWhere($field, 'like', $pattern);
                }
            });
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }

        return view('trabajadores.index', ['trabajadores' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Trabajador::class);

        return view('trabajadores.form', ['trabajador' => new Trabajador(['activo' => true])]);
    }

    public function store(SolicitudTrabajador $request, GuardarTrabajador $accion): RedirectResponse
    {
        $trabajador = $accion->handle($request->user(), null, $request->validated());

        return redirect()->route('trabajadores.show', $trabajador)->with('estado', 'Trabajador registrado.');
    }

    public function show(Trabajador $trabajador): View
    {
        Gate::authorize('view', $trabajador);

        return view('trabajadores.show', ['trabajador' => $trabajador->load('creador', 'modificador')]);
    }

    public function edit(Trabajador $trabajador): View
    {
        Gate::authorize('update', $trabajador);

        return view('trabajadores.form', ['trabajador' => $trabajador]);
    }

    public function update(SolicitudTrabajador $request, Trabajador $trabajador, GuardarTrabajador $accion): RedirectResponse
    {
        $trabajador = $accion->handle($request->user(), $trabajador, $request->validated());

        return redirect()->route('trabajadores.show', $trabajador)->with('estado', 'Trabajador actualizado.');
    }

    public function estado(SolicitudEstadoTrabajador $request, Trabajador $trabajador, GuardarTrabajador $accion): RedirectResponse
    {
        $accion->handle($request->user(), $trabajador, $request->validated(), statusOnly: true);

        return redirect()->route('trabajadores.index')->with('estado', 'Estado del trabajador actualizado.');
    }
}
