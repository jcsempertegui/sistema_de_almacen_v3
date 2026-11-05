<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarCategoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudCategoria;
use App\Http\Requests\Catalogo\SolicitudEstadoCategoria;
use App\Http\Requests\SolicitudBusqueda;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorCategoria extends Controller
{
    public function index(SolicitudBusqueda $request): View
    {
        Gate::authorize('viewAny', Categoria::class);
        $filters = $request->validated();
        $query = Categoria::orderBy('nombre')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('codigo', 'like', $pattern)->orWhere('nombre', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }

        return view('catalogo.categorias.index', ['categorias' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Categoria::class);

        return view('catalogo.categorias.form', ['categoria' => new Categoria]);
    }

    public function edit(Categoria $categoria): View
    {
        Gate::authorize('update', $categoria);

        return view('catalogo.categorias.form', ['categoria' => $categoria]);
    }

    public function store(SolicitudCategoria $request, GuardarCategoria $accion): RedirectResponse
    {
        $accion->handle($request->user(), null, $request->validated());

        return redirect()->route('categorias.index')->with('estado', 'Categoría registrada.');
    }

    public function update(SolicitudCategoria $request, Categoria $categoria, GuardarCategoria $accion): RedirectResponse
    {
        $accion->handle($request->user(), $categoria, $request->validated());

        return redirect()->route('categorias.index')->with('estado', 'Categoría actualizada.');
    }

    public function estado(SolicitudEstadoCategoria $request, Categoria $categoria, GuardarCategoria $accion): RedirectResponse
    {
        $accion->handle($request->user(), $categoria, $request->validated(), statusOnly: true);

        return redirect()->route('categorias.index')->with('estado', 'Estado de la categoría actualizado.');
    }
}
