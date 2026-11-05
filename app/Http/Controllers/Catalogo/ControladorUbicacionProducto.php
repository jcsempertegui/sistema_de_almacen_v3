<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarUbicacionProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudBusquedaUbicacionProducto;
use App\Http\Requests\Catalogo\SolicitudEstadoUbicacionProducto;
use App\Http\Requests\Catalogo\SolicitudUbicacionProducto;
use App\Models\Producto;
use App\Models\UbicacionProducto;
use App\Services\JerarquiaUbicaciones;
use Illuminate\Support\Facades\Gate;

class ControladorUbicacionProducto extends Controller
{
    public function index(SolicitudBusquedaUbicacionProducto $request, Producto $producto, JerarquiaUbicaciones $hierarchy)
    {
        $query = $producto->ubicacionesProducto()->with('ubicacion')->orderBy('id');
        $filters = $request->validated();
        if ($filters['estado'] ?? null) {
            $query->where('activo', $filters['estado'] === 'active');
        }
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('ubicacion', fn ($q) => $q->where(fn ($q) => $q->where('codigo', 'like', $pattern)->orWhere('nombre', 'like', $pattern)));
        }

        return view('catalogo.ubicaciones-producto.index', ['producto' => $producto, 'links' => $query->paginate(20)->withQueryString(), 'paths' => $hierarchy->paths($hierarchy->catalog())]);
    }

    public function create(Producto $producto, JerarquiaUbicaciones $hierarchy)
    {
        Gate::authorize('create', UbicacionProducto::class);
        $catalog = $hierarchy->catalog();

        return view('catalogo.ubicaciones-producto.form', ['producto' => $producto, 'ubicaciones' => $catalog->where('activo', true)->where('permite_almacenar', true), 'paths' => $hierarchy->paths($catalog)]);
    }

    public function store(SolicitudUbicacionProducto $request, Producto $producto, GuardarUbicacionProducto $accion)
    {
        $accion->handle($request->user(), $producto, null, $request->validated());

        return $this->saved($request->user(), $producto);
    }

    public function estado(SolicitudEstadoUbicacionProducto $request, Producto $producto, UbicacionProducto $ubicacionProducto, GuardarUbicacionProducto $accion)
    {
        $accion->handle($request->user(), $producto, $ubicacionProducto, $request->validated());

        return $this->saved($request->user(), $producto);
    }

    private function saved($actor, Producto $producto)
    {
        return redirect($actor->can('viewAny', UbicacionProducto::class) ? route('ubicaciones-producto.index', $producto) : route('dashboard'))->with('estado', 'Asociación de ubicación guardada.');
    }
}
