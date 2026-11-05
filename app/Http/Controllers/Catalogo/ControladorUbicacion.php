<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarUbicacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudBusquedaUbicacion;
use App\Http\Requests\Catalogo\SolicitudEstadoUbicacion;
use App\Http\Requests\Catalogo\SolicitudUbicacion;
use App\Models\Ubicacion;
use App\Services\JerarquiaUbicaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorUbicacion extends Controller
{
    public function index(SolicitudBusquedaUbicacion $request, JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('viewAny', Ubicacion::class);
        $filters = $request->validated();
        $query = Ubicacion::orderBy('codigo')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('codigo', 'like', $pattern)->orWhere('nombre', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }
        if ($tipo = $filters['tipo'] ?? null) {
            $query->where('tipo', $tipo);
        }
        if ($filters['roots_only'] ?? false) {
            $query->whereNull('padre_id');
        } elseif ($padre = $filters['padre_id'] ?? null) {
            $query->where('padre_id', $padre);
        }
        $data = DB::transaction(function () use ($hierarchy, $query) {
            // Keep the page, counts and ancestry in the same REPEATABLE-READ
            // snapshot when a ubicacion is created or reparented concurrently.
            $catalog = $hierarchy->catalog();

            return ['ubicaciones' => $query->paginate(20)->withQueryString(), 'parents' => $catalog->whereIn('id', $catalog->pluck('padre_id')->filter()->unique()), 'paths' => $hierarchy->paths($catalog)];
        });

        return view('catalogo.ubicaciones.index', $data);
    }

    public function tree(JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('viewAny', Ubicacion::class);
        $catalog = $hierarchy->catalog();

        return view('catalogo.ubicaciones.tree', ['rows' => $hierarchy->treeRows($catalog), 'paths' => $hierarchy->paths($catalog)]);
    }

    public function create(JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('create', Ubicacion::class);

        return $this->form(new Ubicacion, $hierarchy);
    }

    public function edit(Ubicacion $ubicacion, JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('update', $ubicacion);

        return $this->form($ubicacion, $hierarchy);
    }

    private function form(Ubicacion $ubicacion, JerarquiaUbicaciones $hierarchy): View
    {
        $catalog = $hierarchy->catalog();
        $excluded = $ubicacion->exists ? $hierarchy->subtreeIds($ubicacion->id) : [];
        $parents = $catalog->where('activo', true)->whereNotIn('id', $excluded);
        $paths = $hierarchy->paths($catalog);

        return view('catalogo.ubicaciones.form', compact('ubicacion', 'parents', 'paths'));
    }

    public function store(SolicitudUbicacion $request, GuardarUbicacion $accion): RedirectResponse
    {
        $accion->handle($request->user(), null, $request->validated());

        return redirect()->route('ubicaciones.index')->with('estado', 'Ubicación registrada.');
    }

    public function update(SolicitudUbicacion $request, Ubicacion $ubicacion, GuardarUbicacion $accion): RedirectResponse
    {
        $accion->handle($request->user(), $ubicacion, $request->validated());

        return redirect()->route('ubicaciones.index')->with('estado', 'Ubicación actualizada.');
    }

    public function estado(SolicitudEstadoUbicacion $request, Ubicacion $ubicacion, GuardarUbicacion $accion): RedirectResponse
    {
        $accion->handle($request->user(), $ubicacion, $request->validated(), statusOnly: true);

        return redirect()->route('ubicaciones.index')->with('estado', 'Estado de la ubicación actualizado.');
    }
}
