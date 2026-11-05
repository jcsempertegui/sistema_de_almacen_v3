<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarAtributoCatalogo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudAtributoCatalogo;
use App\Http\Requests\Catalogo\SolicitudBusquedaAtributo;
use App\Http\Requests\Catalogo\SolicitudEstadoAtributoCatalogo;
use App\Models\AtributoCatalogo;
use App\Models\UnidadMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ControladorAtributoCatalogo extends Controller
{
    public function index(SolicitudBusquedaAtributo $request): View
    {
        Gate::authorize('viewAny', AtributoCatalogo::class);
        $filters = $request->validated();
        $query = AtributoCatalogo::with('unidadMedida')->orderBy('nombre')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('codigo', 'like', $pattern)->orWhere('nombre', 'like', $pattern));
        }
        if ($estado = $filters['estado'] ?? null) {
            $query->where('activo', $estado === 'active');
        }
        if ($tipo = $filters['tipo_dato'] ?? null) {
            $query->where('tipo_dato', $tipo);
        }

        return view('catalogo.atributos.index', ['atributos' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', AtributoCatalogo::class);

        return $this->form(new AtributoCatalogo);
    }

    public function edit(AtributoCatalogo $atributoCatalogo): View
    {
        Gate::authorize('update', $atributoCatalogo);

        return $this->form($atributoCatalogo);
    }

    private function form(AtributoCatalogo $atributo): View
    {
        $unidades = UnidadMedida::where('activo', true)->orWhere('id', $atributo->unidad_medida_id)->orderBy('nombre')->get();

        return view('catalogo.atributos.form', compact('atributo', 'unidades'));
    }

    public function store(SolicitudAtributoCatalogo $request, GuardarAtributoCatalogo $accion): RedirectResponse
    {
        $accion->handle($request->user(), null, $request->validated());

        return redirect()->route('atributos.index')->with('estado', 'Atributo técnico registrado.');
    }

    public function update(SolicitudAtributoCatalogo $request, AtributoCatalogo $atributoCatalogo, GuardarAtributoCatalogo $accion): RedirectResponse
    {
        $accion->handle($request->user(), $atributoCatalogo, $request->validated());

        return redirect()->route('atributos.index')->with('estado', 'Atributo técnico actualizado.');
    }

    public function estado(SolicitudEstadoAtributoCatalogo $request, AtributoCatalogo $atributoCatalogo, GuardarAtributoCatalogo $accion): RedirectResponse
    {
        $accion->handle($request->user(), $atributoCatalogo, $request->validated(), statusOnly: true);

        return redirect()->route('atributos.index')->with('estado', 'Estado del atributo actualizado.');
    }
}
