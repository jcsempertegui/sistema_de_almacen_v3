<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitudCorreccion;
use App\Models\CorreccionInventario;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UnidadIndividual;
use App\Services\CorreccionesInventario;
use App\Support\ValidacionOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ControladorCorreccion extends Controller
{
    private function prefix(string $clase): string
    {
        abort_unless(in_array($clase, ['adjustment', 'compensation']), 404);

        return $clase === 'adjustment' ? 'adjustments' : 'compensations';
    }

    private function documento(string $clase, int $id): CorreccionInventario
    {
        $this->prefix($clase);

        return CorreccionInventario::where('tipo', $clase)->findOrFail($id);
    }

    public function index(Request $r, string $clase)
    {
        Gate::authorize($this->prefix($clase).'.view');
        $filters = ValidacionOperacion::make($r->all(), ['estado' => 'nullable|in:draft,requested,authorized,rejected,cancelled,executed', 'q' => 'nullable|string|max:100'])->validate();
        $documents = CorreccionInventario::where('tipo', $clase)->when($filters['estado'] ?? null, fn ($q, $s) => $q->where('estado', $s))->when($filters['q'] ?? null, fn ($q, $s) => $q->where('motivo', 'like', '%'.$s.'%'))->latest('id')->paginate(30)->withQueryString();

        return view('control.corrections.index', compact('clase', 'documents'));
    }

    public function create(string $clase)
    {
        Gate::authorize($this->prefix($clase).'.create');

        return $this->form($clase, new CorreccionInventario);
    }

    public function edit(string $clase, int $documento)
    {
        $doc = $this->documento($clase, $documento);
        Gate::authorize('update', $doc);

        return $this->form($clase, $doc);
    }

    private function form(string $clase, CorreccionInventario $documento)
    {
        $rows = $documento->exists ? $documento->lineas()->with('unidades')->get()->map(fn ($l) => $l->toArray() + ['unit_ids' => $l->unidades->pluck('id')->all()])->all() : [[]];

        return view('control.corrections.form', compact('clase', 'documento', 'rows') + ['productos' => Producto::orderBy('codigo_interno')->get(), 'ubicaciones' => Ubicacion::orderBy('codigo')->get(), 'unidades' => UnidadIndividual::with('producto')->orderBy('identificador')->get()]);
    }

    public function store(SolicitudCorreccion $r, string $clase)
    {
        $doc = app(CorreccionesInventario::class)->save($r->user(), null, ['tipo' => $clase] + $r->validated());

        return to_route('corrections.show', [$clase, $doc->id])->with('estado', 'Corrección guardada sin afectar existencias.');
    }

    public function update(SolicitudCorreccion $r, string $clase, int $documento)
    {
        $doc = $this->documento($clase, $documento);
        Gate::authorize('update', $doc);
        $doc = app(CorreccionesInventario::class)->save($r->user(), $doc, ['tipo' => $clase] + $r->validated());

        return to_route('corrections.show', [$clase, $doc->id])->with('estado', 'Cambios guardados; las autorizaciones anteriores quedaron revocadas.');
    }

    public function show(string $clase, int $documento)
    {
        $doc = $this->documento($clase, $documento);
        Gate::authorize('view', $doc);

        return view('control.corrections.show', ['documento' => $doc->load('lineas.unidades', 'lineas.producto', 'lineas.ubicacion', 'autorizaciones'), 'clase' => $clase]);
    }

    public function transition(Request $r, string $clase, int $documento, string $accion)
    {
        $doc = $this->documento($clase, $documento);
        $data = ValidacionOperacion::make($r->all(), ['version_bloqueo' => 'required|integer|min:1', 'motivo' => 'nullable|string|max:1000'])->validate();
        $svc = app(CorreccionesInventario::class);
        $doc = $accion === 'execute' ? $svc->execute($r->user(), $doc, $data['version_bloqueo']) : $svc->transition($r->user(), $doc, $accion, $data['version_bloqueo'], $data['motivo'] ?? null);

        return to_route('corrections.show', [$clase, $doc->id])->with('estado', 'Operación registrada.');
    }
}
