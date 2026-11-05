<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarAtributoCategoria;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudAtributoCategoria;
use App\Http\Requests\Catalogo\SolicitudEstadoAtributoCategoria;
use App\Http\Requests\SolicitudBusqueda;
use App\Models\AtributoCatalogo;
use App\Models\AtributoCategoria;
use App\Models\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorAtributoCategoria extends Controller
{
    public function index(SolicitudBusqueda $request, Categoria $categoria): View
    {
        Gate::authorize('viewAny', AtributoCategoria::class);
        $filters = $request->validated();
        $query = $categoria->atributosCategoria()->with('atributo.unidadMedida')->orderBy('orden_presentacion')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('atributo', fn ($query) => $query->where('codigo', 'like', $pattern)->orWhere('nombre', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }

        return view('catalogo.atributos-categoria.index', ['categoria' => $categoria, 'associations' => $query->paginate(20)->withQueryString()]);
    }

    public function create(Categoria $categoria): View
    {
        Gate::authorize('create', AtributoCategoria::class);

        return $this->form($categoria, new AtributoCategoria);
    }

    public function edit(Categoria $categoria, AtributoCategoria $atributoCategoria): View
    {
        Gate::authorize('update', $atributoCategoria);

        return $this->form($categoria, $atributoCategoria);
    }

    private function form(Categoria $categoria, AtributoCategoria $association): View
    {
        $atributos = AtributoCatalogo::where('activo', true)
            ->whereDoesntHave('atributosCategoria', fn ($query) => $query->where('categoria_id', $categoria->id))
            ->with('unidadMedida')->orderBy('nombre')->get();

        return view('catalogo.atributos-categoria.form', compact('categoria', 'association', 'atributos'));
    }

    public function store(SolicitudAtributoCategoria $request, Categoria $categoria, GuardarAtributoCategoria $accion): RedirectResponse
    {
        $accion->handle($request->user(), $categoria, null, $request->validated());

        return redirect()->route('atributos-categoria.index', $categoria)->with('estado', 'Atributo asociado a la categoría.');
    }

    public function update(SolicitudAtributoCategoria $request, Categoria $categoria, AtributoCategoria $atributoCategoria, GuardarAtributoCategoria $accion): RedirectResponse
    {
        $accion->handle($request->user(), $categoria, $atributoCategoria, $request->validated());

        return redirect()->route('atributos-categoria.index', $categoria)->with('estado', 'Configuración del atributo actualizada.');
    }

    public function estado(SolicitudEstadoAtributoCategoria $request, Categoria $categoria, AtributoCategoria $atributoCategoria, GuardarAtributoCategoria $accion): RedirectResponse
    {
        $accion->handle($request->user(), $categoria, $atributoCategoria, $request->validated(), statusOnly: true);

        return redirect()->route('atributos-categoria.index', $categoria)->with('estado', 'Estado de la asociación actualizado.');
    }
}
