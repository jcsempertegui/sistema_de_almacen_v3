<?php

namespace App\Http\Controllers\OperacionesTrabajadores;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Entregas\ConfirmarEntrega;
use App\Actions\Entregas\GuardarEntrega;
use App\Actions\OperacionesTrabajadores\CancelarOperacionTrabajador;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Http\Controllers\Controller;
use App\Http\Requests\OperacionesTrabajadores\SolicitudOperacionTrabajador;
use App\Models\AutorizacionRegularizacionDevolucion;
use App\Models\DocumentoOperacionTrabajador;
use App\Models\ExistenciaUbicacion;
use App\Models\ItemDevolucionHerramienta;
use App\Models\ItemPrestamoHerramienta;
use App\Models\PrestamoHerramienta;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Ubicacion;
use App\Models\UnidadIndividual;
use App\Services\RegularizacionDevolucion;
use App\Support\FormularioOperacion;
use App\Support\TiposOperacionTrabajador;
use App\Support\ValidacionOperacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ControladorOperacionTrabajador extends Controller
{
    private function validated(Request $request, array $rules): array
    {
        return ValidacionOperacion::make($request->all(), $rules)->validate();
    }

    private function model(string $clase): string
    {
        return TiposOperacionTrabajador::MODELS[$clase] ?? abort(404);
    }

    private function documento(string $clase, int $documento): DocumentoOperacionTrabajador
    {
        return $this->model($clase)::findOrFail($documento);
    }

    public function index(Request $request, string $clase)
    {
        Gate::authorize($clase.'.view');
        $filters = $this->validated($request, ['trabajador_id' => 'nullable|integer|min:1', 'estado' => 'nullable|in:draft,confirmed,cancelled', 'q' => 'nullable|string|max:100', 'pending' => 'nullable|in:1']);
        $query = $this->model($clase)::with('trabajador')->when($filters['trabajador_id'] ?? null, fn ($q, $id) => $q->where('trabajador_id', $id))->when($filters['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('trabajador', fn ($w) => $w->where(fn ($w) => $w->where('nombres', 'like', '%'.$term.'%')->orWhere('apellidos', 'like', '%'.$term.'%')->orWhere('codigo', 'like', '%'.$term.'%'))));
        if ($clase === 'loans' && ($filters['pending'] ?? null)) {
            $query->where('estado', 'confirmed')->whereHas('lineas', fn ($q) => $q->pending());
        }

        return view('operaciones-trabajadores.index', ['clase' => $clase, 'label' => TiposOperacionTrabajador::LABELS[$clase], 'documents' => $query->latest('id')->paginate(30)->withQueryString()]);
    }

    public function create(Request $request, string $clase)
    {
        Gate::authorize($clase.'.create');
        $documento = new ($this->model($clase));
        if ($clase === 'returns' && $request->filled('prestamo_id')) {
            $data = $this->validated($request, ['prestamo_id' => 'required|integer|min:1']);
            $prestamo = PrestamoHerramienta::where('estado', 'confirmed')->whereHas('lineas', fn ($q) => $q->pending())->findOrFail($data['prestamo_id']);
            $documento->prestamo_id = $prestamo->id;
        }

        return $this->form($clase, $documento);
    }

    public function edit(string $clase, int $documento)
    {
        $doc = $this->documento($clase, $documento);
        Gate::authorize('update', $doc);

        return $this->form($clase, $doc);
    }

    private function form(string $clase, DocumentoOperacionTrabajador $doc)
    {
        $rows = $doc->exists ? $doc->lineas()->get()->map(function ($line) use ($clase) {
            $data = $line->toArray();
            if ($clase === 'deliveries') {
                $data['unit_ids'] = $line->unidades()->pluck('unidades_individuales.id')->all();
            }

            return $data;
        })->all() : [[]];

        $rows = FormularioOperacion::rows($rows);

        return view('operaciones-trabajadores.form', ['clase' => $clase, 'label' => TiposOperacionTrabajador::LABELS[$clase], 'documento' => $doc, 'rows' => $rows,
            'trabajadores' => Trabajador::where('activo', true)->orWhere('id', $doc->trabajador_id)->orderBy('apellidos')->get(),
            'productos' => Producto::where('activo', true)->where('prestable', false)->orderBy('codigo_interno')->get(),
            'ubicaciones' => Ubicacion::where('activo', true)->where('permite_almacenar', true)->orderBy('codigo')->get(),
            'unidades' => UnidadIndividual::with('producto')->where('activo', true)->whereHas('producto', fn ($q) => $q->where('activo', true)->where('prestable', $clase === 'loans'))->orderBy('identificador')->get(),
            'loans' => PrestamoHerramienta::with('trabajador')->where('estado', 'confirmed')->latest('id')->get(),
            'loanItems' => ItemPrestamoHerramienta::with('unidad', 'producto')->whereHas('documento', fn ($q) => $q->where('estado', 'confirmed'))->pending()->get()]);
    }

    public function store(SolicitudOperacionTrabajador $request, string $clase)
    {
        $class = ['deliveries' => GuardarEntrega::class, 'loans' => GuardarPrestamo::class, 'returns' => GuardarDevolucion::class][$clase];
        $doc = app($class)->handle($request->user(), null, $request->validated());

        return to_route('operaciones-trabajadores.show', [$clase, $doc->id])->with('estado', 'Borrador registrado. No modifica existencias.');
    }

    public function update(SolicitudOperacionTrabajador $request, string $clase, int $documento)
    {
        $class = ['deliveries' => GuardarEntrega::class, 'loans' => GuardarPrestamo::class, 'returns' => GuardarDevolucion::class][$clase];
        $doc = app($class)->handle($request->user(), $this->documento($clase, $documento), $request->validated());

        return to_route('operaciones-trabajadores.show', [$clase, $doc->id])->with('estado', 'Borrador actualizado.');
    }

    public function show(string $clase, int $documento)
    {
        $doc = $this->documento($clase, $documento);
        Gate::authorize('view', $doc);
        $relations = ['lineas.producto', 'lineas.ubicacion'];
        $relations[] = $clase === 'deliveries' ? 'lineas.unidades' : 'lineas.unidad';
        if ($clase === 'returns') {
            $relations[] = 'lineas.autorizaciones.eventos';
        }

        return view('operaciones-trabajadores.show', ['clase' => $clase, 'label' => TiposOperacionTrabajador::LABELS[$clase], 'documento' => $doc->load($relations)]);
    }

    public function confirm(Request $request, string $clase, int $documento)
    {
        $data = $this->validated($request, ['version_bloqueo' => 'required|integer|min:1']);
        $class = ['deliveries' => ConfirmarEntrega::class, 'loans' => ConfirmarPrestamo::class, 'returns' => ConfirmarDevolucion::class][$clase];
        $doc = app($class)->handle($request->user(), $this->documento($clase, $documento), $data['version_bloqueo']);

        return to_route('operaciones-trabajadores.show', [$clase, $doc->id])->with('estado', 'Operación física confirmada.');
    }

    public function cancel(Request $request, string $clase, int $documento)
    {
        $data = $this->validated($request, ['version_bloqueo' => 'required|integer|min:1', 'motivo' => 'required|string|max:500']);
        $doc = app(CancelarOperacionTrabajador::class)->handle($request->user(), $this->documento($clase, $documento), $data['version_bloqueo'], $data['motivo']);

        return to_route('operaciones-trabajadores.show', [$clase, $doc->id])->with('estado', 'Borrador cancelado y conservado.');
    }

    public function requestRegularization(Request $request, ItemDevolucionHerramienta $item)
    {
        $data = $this->validated($request, ['version_bloqueo' => 'required|integer|min:1', 'motivo' => 'required|string|max:1000']);
        app(RegularizacionDevolucion::class)->request($request->user(), $item, $data['version_bloqueo'], $data['motivo']);

        return to_route('operaciones-trabajadores.show', ['returns', $item->devolucion_id])->with('estado', 'Solicitud específica registrada.');
    }

    public function decideRegularization(Request $request, AutorizacionRegularizacionDevolucion $authorization, string $decision)
    {
        $data = $this->validated($request, ['motivo' => 'required|string|max:1000']);
        app(RegularizacionDevolucion::class)->decide($request->user(), $authorization, $decision === 'approve', $data['motivo']);

        return to_route('operaciones-trabajadores.show', ['returns', $authorization->item->devolucion_id])->with('estado', 'Decisión registrada.');
    }

    public function availability(Request $request)
    {
        Gate::authorize('availability.view');
        $data = $this->validated($request, ['q' => 'nullable|string|max:100', 'producto_id' => 'nullable|integer|min:1', 'ubicacion_id' => 'nullable|integer|min:1']);
        $positions = ExistenciaUbicacion::with('producto.unidadMedida', 'ubicacion')->when($data['producto_id'] ?? null, fn ($q, $id) => $q->where('producto_id', $id))->when($data['ubicacion_id'] ?? null, fn ($q, $id) => $q->where('ubicacion_id', $id))->when($data['q'] ?? null, fn ($q, $term) => $q->whereHas('producto', fn ($p) => $p->where(fn ($w) => $w->where('codigo_interno', 'like', '%'.$term.'%')->orWhere('nombre', 'like', '%'.$term.'%'))))->orderBy('producto_id')->paginate(30)->withQueryString();
        $individuals = DB::table('posiciones_unidad_individual as pos')->join('unidades_individuales as u', 'u.id', '=', 'pos.unidad_individual_id')->join('productos as p', 'p.id', '=', 'pos.producto_id')->leftJoin('ubicaciones as l', 'l.id', '=', 'pos.ubicacion_id')->leftJoin('custodias_unidad_individual as c', 'c.unidad_individual_id', '=', 'u.id')->leftJoin('items_prestamo_herramienta as li', 'li.id', '=', 'c.item_prestamo_actual_id')->leftJoin('prestamos_herramientas as prestamo', 'prestamo.id', '=', 'li.prestamo_id')->leftJoin('trabajadores as w', 'w.id', '=', 'prestamo.trabajador_id')->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('u.identificador', 'like', '%'.$term.'%')->orWhere('u.numero_serie', 'like', '%'.$term.'%')->orWhere('p.codigo_interno', 'like', '%'.$term.'%')))->when($data['producto_id'] ?? null, fn ($q, $id) => $q->where('pos.producto_id', $id))->when($data['ubicacion_id'] ?? null, fn ($q, $id) => $q->where('pos.ubicacion_id', $id))->select('u.identificador', 'u.activo', 'p.codigo_interno', 'pos.presente', 'l.codigo', 'c.item_prestamo_actual_id', 'prestamo.id as prestamo_id', 'w.nombres', 'w.apellidos')->orderBy('u.identificador')->paginate(30, ['*'], 'units_page')->withQueryString();
        $allLocations = Ubicacion::all()->keyBy('id');
        foreach ($positions as $position) {
            $active = true;
            $seen = [];
            $ubicacion = $position->ubicacion;
            while ($ubicacion) {
                if (isset($seen[$ubicacion->id]) || ! $ubicacion->activo) {
                    $active = false;
                    break;
                }$seen[$ubicacion->id] = true;
                $ubicacion = $ubicacion->padre_id ? $allLocations->get($ubicacion->padre_id) : null;
            }
            $pair = DB::table('ubicaciones_producto')->where('producto_id', $position->producto_id)->where('ubicacion_id', $position->ubicacion_id)->first();
            $ready = $active && $position->ubicacion->permite_almacenar && $position->producto->activo && $position->producto->unidadMedida->activo && $pair?->activo;
            $position->operational_quantity = ! $ready ? '0' : ($position->producto->modo_control === 'quantity' ? $position->cantidad : (string) DB::table('posiciones_unidad_individual as pos')->join('unidades_individuales as u', 'u.id', '=', 'pos.unidad_individual_id')->leftJoin('custodias_unidad_individual as c', 'c.unidad_individual_id', '=', 'u.id')->where('pos.producto_id', $position->producto_id)->where('pos.ubicacion_id', $position->ubicacion_id)->where('pos.presente', true)->where('u.activo', true)->whereNull('c.item_prestamo_actual_id')->count());
        }

        return view('operaciones-trabajadores.availability', compact('positions', 'individuals') + ['productChoices' => Producto::orderBy('codigo_interno')->get(), 'locationChoices' => Ubicacion::orderBy('codigo')->get()]);
    }
}
