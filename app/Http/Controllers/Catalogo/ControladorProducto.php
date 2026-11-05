<?php

namespace App\Http\Controllers\Catalogo;

use App\Actions\Catalogo\GuardarProducto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\SolicitudBusquedaProducto;
use App\Http\Requests\Catalogo\SolicitudCategoriaProducto;
use App\Http\Requests\Catalogo\SolicitudEstadoProducto;
use App\Http\Requests\Catalogo\SolicitudProducto;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\BuscadorDuplicadosProducto;
use App\Services\JerarquiaUbicaciones;
use App\Services\ValidadorAtributosProducto;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ControladorProducto extends Controller
{
    public function index(SolicitudBusquedaProducto $request)
    {
        $filters = $request->validated();
        $query = Producto::with(['categoria', 'unidadMedida'])->orderBy('codigo_interno')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('codigo_interno', 'like', $pattern)->orWhere('nombre', 'like', $pattern)->orWhere('marca', 'like', $pattern)->orWhere('referencia_fabricante', 'like', $pattern));
        }
        foreach (['categoria_id', 'unidad_medida_id', 'modo_control'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['estado'] ?? null) {
            $query->where('activo', $filters['estado'] === 'active');
        }

        return view('catalogo.productos.index', ['productos' => $query->paginate(20)->withQueryString(), 'categorias' => Categoria::orderBy('nombre')->get(), 'unidades' => UnidadMedida::orderBy('nombre')->get()]);
    }

    public function create(SolicitudCategoriaProducto $request)
    {
        return $this->form(new Producto, $request);
    }

    public function edit(SolicitudCategoriaProducto $request, Producto $producto)
    {
        return $this->form($producto, $request);
    }

    private function form(Producto $producto, SolicitudCategoriaProducto $request)
    {
        $categoryId = (int) old('categoria_id', $request->validated('categoria_id') ?? $producto->categoria_id);
        $definitions = $categoryId ? app(ValidadorAtributosProducto::class)->definitions($categoryId) : collect();
        $existing = $producto->exists ? $producto->valoresAtributos()->with('atributoCategoria.atributo')->get()->keyBy('atributo_categoria_id') : collect();
        $catalog = app(JerarquiaUbicaciones::class)->catalog();

        return view('catalogo.productos.form', [
            'producto' => $producto, 'categoryId' => $categoryId, 'definitions' => $definitions, 'existing' => $existing,
            'categorias' => Categoria::where('activo', true)->orWhere('id', $producto->categoria_id)->orderBy('nombre')->get(),
            'unidades' => UnidadMedida::where('activo', true)->orWhere('id', $producto->unidad_medida_id)->orderBy('nombre')->get(),
            'ubicaciones' => $catalog->where('activo', true)->where('permite_almacenar', true), 'paths' => app(JerarquiaUbicaciones::class)->paths($catalog),
        ]);
    }

    public function preview(SolicitudProducto $request, ValidadorAtributosProducto $validator, BuscadorDuplicadosProducto $finder, ?Producto $producto = null)
    {
        return $this->attempt($request, function () use ($request, $validator, $finder, $producto) {
            $data = $request->validated();
            $draft = $producto ? clone $producto : new Producto;
            $draft->fill($data);
            $definitions = $validator->definitions($draft->categoria_id);
            $values = $validator->normalize($draft, $data['values'] ?? [], $definitions);
            $candidates = $finder->candidates($draft, $values, $definitions);
            $token = $finder->token($data, $values, $definitions, $candidates);
            $hasCandidates = $candidates->isNotEmpty();
            if (! $request->user()->can('viewAny', Producto::class)) {
                $candidates = collect();
            }

            return view('catalogo.productos.preview', compact('draft', 'data', 'definitions', 'values', 'candidates', 'token', 'hasCandidates'));
        });
    }

    public function show(Producto $producto)
    {
        Gate::authorize('view', $producto);
        $producto->load(['categoria', 'unidadMedida', 'valoresAtributos.atributoCategoria.atributo.unidadMedida', 'valoresAtributos.revisiones.creador']);

        return view('catalogo.productos.show', compact('producto'));
    }

    public function store(SolicitudProducto $request, GuardarProducto $accion)
    {
        $producto = $this->attempt($request, fn () => $accion->handle($request->user(), null, $request->validated()));

        return $this->saved($request->user(), $producto, 'Producto registrado.');
    }

    public function update(SolicitudProducto $request, Producto $producto, GuardarProducto $accion)
    {
        $producto = $this->attempt($request, fn () => $accion->handle($request->user(), $producto, $request->validated()));

        return $this->saved($request->user(), $producto, 'Producto actualizado.');
    }

    public function estado(SolicitudEstadoProducto $request, Producto $producto, GuardarProducto $accion)
    {
        $producto = $accion->handle($request->user(), $producto, $request->validated(), statusOnly: true);

        return $this->saved($request->user(), $producto, 'Estado del producto actualizado.');
    }

    private function saved($actor, Producto $producto, string $message)
    {
        return redirect($actor->can('view', $producto) ? route('productos.show', $producto) : route('dashboard'))->with('estado', $message);
    }

    private function attempt(SolicitudProducto $request, Closure $operation)
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($request->formUrl());
        }
    }
}
