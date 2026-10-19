<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CancelInventoryDocument;
use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryDocumentRequest;
use App\Models\IndividualUnit;
use App\Models\InventoryDocument;
use App\Models\Location;
use App\Models\Product;
use App\Support\InventoryValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryDocumentController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', InventoryDocument::class);
        $filters = InventoryValidation::validate($request, ['q' => ['nullable', 'string', 'max:100'], 'kind' => ['nullable', 'in:receipt,issue,opening'], 'status' => ['nullable', 'in:draft,confirmed,cancelled']]);
        $documents = InventoryDocument::query()
            ->whereIn('kind', array_keys(array_filter(InventoryDocument::KINDS, fn ($label, $kind) => $request->user()->hasPermission($kind === 'opening' ? 'initial_stock.view' : 'remittances.view'), ARRAY_FILTER_USE_BOTH)))
            ->when($filters['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->when($filters['status'] ?? null, fn ($q, $state) => $q->where('status', $state))
            ->when($filters['q'] ?? null, function ($q, $search) {
                $q->where(function ($w) use ($search) {
                    $w->where('external_reference', 'like', '%'.$search.'%')->orWhere('counterparty_name', 'like', '%'.$search.'%')->orWhere('id', ctype_digit($search) ? $search : 0);
                    if (preg_match('/^RG-(IN|EG|AP)-([0-9]+)$/D', mb_strtoupper($search), $parts)) {
                        $kind = ['IN' => 'receipt', 'EG' => 'issue', 'AP' => 'opening'][$parts[1]];
                        $w->orWhere(fn ($code) => $code->where('id', ltrim($parts[2], '0') ?: '0')->where('kind', $kind));
                    }
                });
            })
            ->latest('id')->paginate(20)->withQueryString();

        return view('inventory.documents.index', compact('documents'));
    }

    public function create(Request $request)
    {
        InventoryValidation::validate($request, ['kind' => ['required', 'in:receipt,issue,opening']]);
        $document = new InventoryDocument(['kind' => $request->input('kind'), 'document_date' => now(InventoryDocument::DISPLAY_TIMEZONE)->toDateString(), 'status' => 'draft']);
        Gate::authorize($document->permission('create'));

        return $this->form($document);
    }

    public function store(InventoryDocumentRequest $request, SaveInventoryDocument $save)
    {
        $document = $save->handle($request->user(), null, $request->all());

        return redirect()->route('inventory-documents.show', $document)->with('status', 'Borrador guardado. Todavía no modifica existencias.');
    }

    public function edit(InventoryDocument $document)
    {
        Gate::authorize('update', $document);

        return $this->form($document);
    }

    public function update(InventoryDocumentRequest $request, InventoryDocument $document, SaveInventoryDocument $save)
    {
        $document = $save->handle($request->user(), $document, $request->all());

        return redirect()->route('inventory-documents.show', $document)->with('status', 'Borrador actualizado; revisa antes de confirmar.');
    }

    public function show(InventoryDocument $document)
    {
        Gate::authorize('view', $document);
        $document->load('lines.product', 'lines.location', 'lines.units');
        $coincidences = $document->external_reference && $document->kind !== 'opening'
            ? InventoryDocument::where('kind', $document->kind)->where('counterparty_id', $document->counterparty_id)->where('external_reference', $document->external_reference)->where('id', '<>', $document->id)->orderBy('id')->get()
            : collect();
        $importCoincidences = $document->source_sha256
            ? InventoryDocument::where('kind', 'opening')->where('source_sha256', $document->source_sha256)->where('id', '<>', $document->id)->orderBy('id')->get()
            : collect();

        return view('inventory.documents.show', compact('document', 'coincidences', 'importCoincidences'));
    }

    public function confirm(Request $request, InventoryDocument $document, ConfirmInventoryDocument $confirm)
    {
        Gate::authorize('confirm', $document);
        InventoryValidation::validate($request, ['lock_version' => ['required', 'integer', 'min:1']]);
        $confirm->handle($request->user(), $document, $request->input('lock_version'));

        return redirect()->route('inventory-documents.show', $document)->with('status', 'Documento confirmado. Sus movimientos se registran una sola vez.');
    }

    public function cancel(Request $request, InventoryDocument $document, CancelInventoryDocument $cancel)
    {
        Gate::authorize('cancel', $document);
        $cancel->handle($request->user(), $document, $request->all());

        return redirect()->route('inventory-documents.show', $document)->with('status', 'Borrador cancelado. No generó movimientos.');
    }

    private function form(InventoryDocument $document)
    {
        $document->load('lines.units');
        $products = Product::with('measurementUnit')->where('is_active', true)->orderBy('internal_code')->get();
        $locations = Location::where('is_active', true)->where('is_storable', true)->orderBy('code')->get();
        $units = IndividualUnit::with('product')->where('is_active', true)->orderBy('identifier')->get();

        return view('inventory.documents.form', compact('document', 'products', 'locations', 'units'));
    }
}
