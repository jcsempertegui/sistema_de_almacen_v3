<?php

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorrectionRequest;
use App\Models\IndividualUnit;
use App\Models\InventoryCorrection;
use App\Models\Location;
use App\Models\Product;
use App\Services\InventoryCorrections;
use App\Support\OperationValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CorrectionController extends Controller
{
    private function prefix(string $kind): string
    {
        abort_unless(in_array($kind, ['adjustment', 'compensation']), 404);

        return $kind === 'adjustment' ? 'adjustments' : 'compensations';
    }

    private function document(string $kind, int $id): InventoryCorrection
    {
        $this->prefix($kind);

        return InventoryCorrection::where('kind', $kind)->findOrFail($id);
    }

    public function index(Request $r, string $kind)
    {
        Gate::authorize($this->prefix($kind).'.view');
        $filters = OperationValidation::make($r->all(), ['status' => 'nullable|in:draft,requested,authorized,rejected,cancelled,executed', 'q' => 'nullable|string|max:100'])->validate();
        $documents = InventoryCorrection::where('kind', $kind)->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->when($filters['q'] ?? null, fn ($q, $s) => $q->where('reason', 'like', '%'.$s.'%'))->latest('id')->paginate(30)->withQueryString();

        return view('control.corrections.index', compact('kind', 'documents'));
    }

    public function create(string $kind)
    {
        Gate::authorize($this->prefix($kind).'.create');

        return $this->form($kind, new InventoryCorrection);
    }

    public function edit(string $kind, int $document)
    {
        $doc = $this->document($kind, $document);
        Gate::authorize('update', $doc);

        return $this->form($kind, $doc);
    }

    private function form(string $kind, InventoryCorrection $document)
    {
        $rows = $document->exists ? $document->lines()->with('units')->get()->map(fn ($l) => $l->toArray() + ['unit_ids' => $l->units->pluck('id')->all()])->all() : [[]];

        return view('control.corrections.form', compact('kind', 'document', 'rows') + ['products' => Product::orderBy('internal_code')->get(), 'locations' => Location::orderBy('code')->get(), 'units' => IndividualUnit::with('product')->orderBy('identifier')->get()]);
    }

    public function store(CorrectionRequest $r, string $kind)
    {
        $doc = app(InventoryCorrections::class)->save($r->user(), null, ['kind' => $kind] + $r->validated());

        return to_route('corrections.show', [$kind, $doc->id])->with('status', 'Corrección guardada sin afectar existencias.');
    }

    public function update(CorrectionRequest $r, string $kind, int $document)
    {
        $doc = $this->document($kind, $document);
        Gate::authorize('update', $doc);
        $doc = app(InventoryCorrections::class)->save($r->user(), $doc, ['kind' => $kind] + $r->validated());

        return to_route('corrections.show', [$kind, $doc->id])->with('status', 'Cambios guardados; las autorizaciones anteriores quedaron revocadas.');
    }

    public function show(string $kind, int $document)
    {
        $doc = $this->document($kind, $document);
        Gate::authorize('view', $doc);

        return view('control.corrections.show', ['document' => $doc->load('lines.units', 'lines.product', 'lines.location', 'authorizations'), 'kind' => $kind]);
    }

    public function transition(Request $r, string $kind, int $document, string $action)
    {
        $doc = $this->document($kind, $document);
        $data = OperationValidation::make($r->all(), ['lock_version' => 'required|integer|min:1', 'reason' => 'nullable|string|max:1000'])->validate();
        $svc = app(InventoryCorrections::class);
        $doc = $action === 'execute' ? $svc->execute($r->user(), $doc, $data['lock_version']) : $svc->transition($r->user(), $doc, $action, $data['lock_version'], $data['reason'] ?? null);

        return to_route('corrections.show', [$kind, $doc->id])->with('status', 'Operación registrada.');
    }
}
