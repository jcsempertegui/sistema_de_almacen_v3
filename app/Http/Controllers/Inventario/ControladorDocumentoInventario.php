<?php

namespace App\Http\Controllers\Inventario;

use App\Actions\Inventario\CancelarDocumentoInventario;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\SolicitudDocumentoInventario;
use App\Models\DocumentoInventario;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UnidadIndividual;
use App\Support\ValidacionInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ControladorDocumentoInventario extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', DocumentoInventario::class);
        $filters = ValidacionInventario::validate($request, ['q' => ['nullable', 'string', 'max:100'], 'tipo' => ['nullable', 'in:receipt,issue,opening'], 'estado' => ['nullable', 'in:draft,confirmed,cancelled']]);
        $documents = DocumentoInventario::query()
            ->whereIn('tipo', array_keys(array_filter(DocumentoInventario::KINDS, fn ($label, $clase) => $request->user()->tienePermiso($clase === 'opening' ? 'initial_stock.view' : 'remittances.view'), ARRAY_FILTER_USE_BOTH)))
            ->when($filters['tipo'] ?? null, fn ($q, $clase) => $q->where('tipo', $clase))
            ->when($filters['estado'] ?? null, fn ($q, $state) => $q->where('estado', $state))
            ->when($filters['q'] ?? null, function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('referencia_externa', 'like', '%'.$search.'%')->orWhere('nombre_contraparte', 'like', '%'.$search.'%')->orWhere('id', ctype_digit($search) ? $search : 0);
                    if (preg_match('/^RG-(IN|EG|AP)-([0-9]+)$/D', mb_strtoupper($search), $parts)) {
                        $clase = ['IN' => 'receipt', 'EG' => 'issue', 'AP' => 'opening'][$parts[1]];
                        $w->orWhere(fn ($codigo) => $codigo->where('id', ltrim($parts[2], '0') ?: '0')->where('tipo', $clase));
                    }
                });
            })
            ->latest('id')->paginate(20)->withQueryString();

        return view('inventario.documents.index', compact('documents'));
    }

    public function create(Request $request)
    {
        ValidacionInventario::validate($request, ['tipo' => ['required', 'in:receipt,issue,opening']]);
        $documento = new DocumentoInventario(['tipo' => $request->input('tipo'), 'fecha_documento' => now(DocumentoInventario::DISPLAY_TIMEZONE)->toDateString(), 'estado' => 'draft']);
        Gate::authorize($documento->permiso('create'));

        return $this->form($documento);
    }

    public function store(SolicitudDocumentoInventario $request, GuardarDocumentoInventario $save)
    {
        $documento = $save->handle($request->user(), null, $request->all());

        return redirect()->route('documentos-inventario.show', $documento)->with('estado', 'Borrador guardado. Todavía no modifica existencias.');
    }

    public function edit(DocumentoInventario $documento)
    {
        Gate::authorize('update', $documento);

        return $this->form($documento);
    }

    public function update(SolicitudDocumentoInventario $request, DocumentoInventario $documento, GuardarDocumentoInventario $save)
    {
        $documento = $save->handle($request->user(), $documento, $request->all());

        return redirect()->route('documentos-inventario.show', $documento)->with('estado', 'Borrador actualizado; revisa antes de confirmar.');
    }

    public function show(DocumentoInventario $documento)
    {
        Gate::authorize('view', $documento);
        $documento->load('lineas.producto', 'lineas.ubicacion', 'lineas.unidades');
        $coincidences = $documento->referencia_externa && $documento->tipo !== 'opening'
            ? DocumentoInventario::where('tipo', $documento->tipo)->where('identificacion_contraparte', $documento->identificacion_contraparte)->where('referencia_externa', $documento->referencia_externa)->where('id', '<>', $documento->id)->orderBy('id')->get()
            : collect();
        $importCoincidences = $documento->sha256_origen
            ? DocumentoInventario::where('tipo', 'opening')->where('sha256_origen', $documento->sha256_origen)->where('id', '<>', $documento->id)->orderBy('id')->get()
            : collect();

        return view('inventario.documents.show', compact('documento', 'coincidences', 'importCoincidences'));
    }

    public function confirm(Request $request, DocumentoInventario $documento, ConfirmarDocumentoInventario $confirm)
    {
        Gate::authorize('confirm', $documento);
        ValidacionInventario::validate($request, ['version_bloqueo' => ['required', 'integer', 'min:1']]);
        $confirm->handle($request->user(), $documento, $request->input('version_bloqueo'));

        return redirect()->route('documentos-inventario.show', $documento)->with('estado', 'Documento confirmado. Sus movimientos se registran una sola vez.');
    }

    public function cancel(Request $request, DocumentoInventario $documento, CancelarDocumentoInventario $cancel)
    {
        Gate::authorize('cancel', $documento);
        $cancel->handle($request->user(), $documento, $request->all());

        return redirect()->route('documentos-inventario.show', $documento)->with('estado', 'Borrador cancelado. No generó movimientos.');
    }

    private function form(DocumentoInventario $documento)
    {
        $documento->load('lineas.unidades');
        $productos = Producto::with('unidadMedida')->where('activo', true)->orderBy('codigo_interno')->get();
        $ubicaciones = Ubicacion::where('activo', true)->where('permite_almacenar', true)->orderBy('codigo')->get();
        $unidades = UnidadIndividual::with('producto')->where('activo', true)->orderBy('identificador')->get();

        return view('inventario.documents.form', compact('documento', 'productos', 'ubicaciones', 'unidades'));
    }
}
