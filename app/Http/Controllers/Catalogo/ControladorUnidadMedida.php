<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarUnidadMedida;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudEstadoUnidadMedida;
use App\Http\Requests\Catalogo\SolicitudUnidadMedida;
use App\Http\Requests\SolicitudBusqueda;
use App\Models\UnidadMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorUnidadMedida extends Controller
{
    public function index(SolicitudBusqueda $request): View
    {
        Gate::authorize('viewAny', UnidadMedida::class);
        $filters = $request->validated();
        $query = UnidadMedida::orderBy('nombre')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('codigo', 'like', $pattern)->orWhere('nombre', 'like', $pattern)->orWhere('simbolo', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }

        return view('catalogo.unidades-medida.index', ['unidades' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', UnidadMedida::class);

        return view('catalogo.unidades-medida.form', ['unidad' => new UnidadMedida]);
    }

    public function edit(UnidadMedida $unidadMedida): View
    {
        Gate::authorize('update', $unidadMedida);

        return view('catalogo.unidades-medida.form', ['unidad' => $unidadMedida]);
    }

    public function store(SolicitudUnidadMedida $request, GuardarUnidadMedida $accion): RedirectResponse
    {
        $accion->handle($request->user(), null, $request->validated());

        return redirect()->route('unidades-medida.index')->with('estado', 'Unidad de medida registrada.');
    }

    public function update(SolicitudUnidadMedida $request, UnidadMedida $unidadMedida, GuardarUnidadMedida $accion): RedirectResponse
    {
        $accion->handle($request->user(), $unidadMedida, $request->validated());

        return redirect()->route('unidades-medida.index')->with('estado', 'Unidad de medida actualizada.');
    }

    public function estado(SolicitudEstadoUnidadMedida $request, UnidadMedida $unidadMedida, GuardarUnidadMedida $accion): RedirectResponse
    {
        $accion->handle($request->user(), $unidadMedida, $request->validated(), statusOnly: true);

        return redirect()->route('unidades-medida.index')->with('estado', 'Estado de la unidad de medida actualizado.');
    }
}
