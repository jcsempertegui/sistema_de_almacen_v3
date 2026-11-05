<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudBusquedaUnidadIndividual;
use App\Http\Requests\Catalogo\SolicitudEstadoUnidadIndividual;
use App\Http\Requests\Catalogo\SolicitudUnidadIndividual;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Services\JerarquiaUbicaciones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorUnidadIndividual extends Controller
{
    public function index(SolicitudBusquedaUnidadIndividual $request, JerarquiaUbicaciones $hierarchy): View
    {
        $filters = $request->validated();
        $query = UnidadIndividual::with(['producto', 'ubicacion'])->orderBy('identificador')->orderBy('id');
        if (($filters['q'] ?? null) !== null && $filters['q'] !== '') {
            $search = $filters['q'];
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('identificador', 'like', $pattern)->orWhere('numero_serie', 'like', $pattern));
        }
        foreach (['producto_id', 'ubicacion_id'] as $key) {
            if ($filters[$key] ?? null) {
                $query->where($key, $filters[$key]);
            }
        }
        if ($filters['estado'] ?? null) {
            $query->where('activo', $filters['estado'] === 'active');
        }

        return view('catalogo.unidades-individuales.index', ['unidades' => $query->paginate(20)->withQueryString(), 'productos' => Producto::where('modo_control', 'individual')->orderBy('codigo_interno')->get(), 'ubicaciones' => Ubicacion::orderBy('codigo')->get(), 'paths' => $hierarchy->paths($hierarchy->catalog())]);
    }

    public function create(Request $request, JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('create', UnidadIndividual::class);
        $filters = $request->validate(['producto_id' => ['nullable', 'integer', 'exists:productos,id']]);
        $unidad = new UnidadIndividual(['producto_id' => $filters['producto_id'] ?? null]);

        return $this->form($unidad, $hierarchy);
    }

    public function edit(UnidadIndividual $unidadIndividual, JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('update', $unidadIndividual);

        return $this->form($unidadIndividual, $hierarchy);
    }

    private function form(UnidadIndividual $unidad, JerarquiaUbicaciones $hierarchy): View
    {
        $productos = Producto::where('activo', true)->where('modo_control', 'individual')->orderBy('codigo_interno')->get();
        $links = UbicacionProducto::with(['ubicacion', 'producto'])->where('activo', true)->whereHas('producto', fn ($q) => $q->where('activo', true)->where('modo_control', 'individual'))->whereHas('ubicacion', fn ($q) => $q->where('activo', true)->where('permite_almacenar', true))->get();
        $paths = $hierarchy->paths($hierarchy->catalog());
        $unidad->load(['producto', 'ubicacion']);

        return view('catalogo.unidades-individuales.form', compact('unidad', 'productos', 'links', 'paths'));
    }

    public function show(UnidadIndividual $unidadIndividual, JerarquiaUbicaciones $hierarchy): View
    {
        Gate::authorize('view', $unidadIndividual);

        return view('catalogo.unidades-individuales.show', ['unidad' => $unidadIndividual->load(['producto', 'ubicacion', 'creador', 'modificador']), 'paths' => $hierarchy->paths($hierarchy->catalog())]);
    }

    public function store(SolicitudUnidadIndividual $request, GuardarUnidadIndividual $accion): RedirectResponse
    {
        $unidad = $accion->handle($request->user(), null, $request->validated());

        return redirect()->route($request->user()->can('view', $unidad) ? 'unidades-individuales.show' : 'dashboard', $request->user()->can('view', $unidad) ? [$unidad] : [])->with('estado', 'Unidad individualizada registrada.');
    }

    public function update(SolicitudUnidadIndividual $request, UnidadIndividual $unidadIndividual, GuardarUnidadIndividual $accion): RedirectResponse
    {
        $unidad = $accion->handle($request->user(), $unidadIndividual, $request->validated());

        return redirect()->route($request->user()->can('view', $unidad) ? 'unidades-individuales.show' : 'dashboard', $request->user()->can('view', $unidad) ? [$unidad] : [])->with('estado', 'Unidad individualizada actualizada.');
    }

    public function estado(SolicitudEstadoUnidadIndividual $request, UnidadIndividual $unidadIndividual, GuardarUnidadIndividual $accion): RedirectResponse
    {
        $unidad = $accion->handle($request->user(), $unidadIndividual, $request->validated(), statusOnly: true);

        return redirect()->route($request->user()->can('view', $unidad) ? 'unidades-individuales.show' : 'dashboard', $request->user()->can('view', $unidad) ? [$unidad] : [])->with('estado', 'Estado de la unidad actualizado.');
    }
}
