<?php

namespace App\Http\Controllers\WorkerOperations;

use App\Actions\Deliveries\ConfirmDelivery;
use App\Actions\Deliveries\SaveDelivery;
use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Actions\WorkerOperations\CancelWorkerOperation;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkerOperations\WorkerOperationRequest;
use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReturnRegularizationAuthorization;
use App\Models\StockPosition;
use App\Models\ToolLoan;
use App\Models\ToolLoanItem;
use App\Models\ToolReturnItem;
use App\Models\Worker;
use App\Models\WorkerOperationDocument;
use App\Services\ReturnRegularization;
use App\Support\OperationForm;
use App\Support\OperationValidation;
use App\Support\WorkerOperationTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WorkerOperationController extends Controller
{
    private function validated(Request $request, array $rules): array
    {
        return OperationValidation::make($request->all(), $rules)->validate();
    }

    private function model(string $kind): string
    {
        return WorkerOperationTypes::MODELS[$kind] ?? abort(404);
    }

    private function document(string $kind, int $document): WorkerOperationDocument
    {
        return $this->model($kind)::findOrFail($document);
    }

    public function index(Request $request, string $kind)
    {
        Gate::authorize($kind.'.view');
        $filters = $this->validated($request, ['worker_id' => 'nullable|integer|min:1', 'status' => 'nullable|in:draft,confirmed,cancelled', 'q' => 'nullable|string|max:100', 'pending' => 'nullable|in:1']);
        $query = $this->model($kind)::with('worker')->when($filters['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('worker', fn ($w) => $w->where(fn ($w) => $w->where('nombres', 'like', '%'.$term.'%')->orWhere('apellidos', 'like', '%'.$term.'%')->orWhere('codigo', 'like', '%'.$term.'%'))));
        if ($kind === 'loans' && ($filters['pending'] ?? null)) {
            $query->where('status', 'confirmed')->whereHas('lines', fn ($q) => $q->whereNotExists(fn ($s) => $s->selectRaw('1')->from('loan_item_settlements')->whereColumn('loan_item_settlements.loan_item_id', 'tool_loan_items.id')));
        }

        return view('worker-operations.index', ['kind' => $kind, 'label' => WorkerOperationTypes::LABELS[$kind], 'documents' => $query->latest('id')->paginate(30)->withQueryString()]);
    }

    public function create(string $kind)
    {
        Gate::authorize($kind.'.create');

        return $this->form($kind, new ($this->model($kind)));
    }

    public function edit(string $kind, int $document)
    {
        $doc = $this->document($kind, $document);
        Gate::authorize('update', $doc);

        return $this->form($kind, $doc);
    }

    private function form(string $kind, WorkerOperationDocument $doc)
    {
        $rows = $doc->exists ? $doc->lines()->get()->map(function ($line) use ($kind) {
            $data = $line->toArray();
            if ($kind === 'deliveries') {
                $data['unit_ids'] = $line->units()->pluck('individual_units.id')->all();
            }

            return $data;
        })->all() : [[]];

        $rows = OperationForm::rows($rows);

        return view('worker-operations.form', ['kind' => $kind, 'label' => WorkerOperationTypes::LABELS[$kind], 'document' => $doc, 'rows' => $rows,
            'workers' => Worker::where('is_active', true)->orWhere('id', $doc->worker_id)->orderBy('apellidos')->get(),
            'products' => Product::where('is_active', true)->where('is_loanable', false)->orderBy('internal_code')->get(),
            'locations' => Location::where('is_active', true)->where('is_storable', true)->orderBy('code')->get(),
            'units' => IndividualUnit::with('product')->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true)->where('is_loanable', $kind === 'loans'))->orderBy('identifier')->get(),
            'loans' => ToolLoan::with('worker')->where('status', 'confirmed')->latest('id')->get(),
            'loanItems' => ToolLoanItem::with('unit', 'product')->whereHas('document', fn ($q) => $q->where('status', 'confirmed'))->whereNotExists(fn ($q) => $q->selectRaw('1')->from('loan_item_settlements')->whereColumn('loan_item_settlements.loan_item_id', 'tool_loan_items.id'))->get()]);
    }

    public function store(WorkerOperationRequest $request, string $kind)
    {
        $class = ['deliveries' => SaveDelivery::class, 'loans' => SaveLoan::class, 'returns' => SaveReturn::class][$kind];
        $doc = app($class)->handle($request->user(), null, $request->validated());

        return to_route('worker-operations.show', [$kind, $doc->id])->with('status', 'Borrador registrado. No modifica existencias.');
    }

    public function update(WorkerOperationRequest $request, string $kind, int $document)
    {
        $class = ['deliveries' => SaveDelivery::class, 'loans' => SaveLoan::class, 'returns' => SaveReturn::class][$kind];
        $doc = app($class)->handle($request->user(), $this->document($kind, $document), $request->validated());

        return to_route('worker-operations.show', [$kind, $doc->id])->with('status', 'Borrador actualizado.');
    }

    public function show(string $kind, int $document)
    {
        $doc = $this->document($kind, $document);
        Gate::authorize('view', $doc);
        $relations = ['lines.product', 'lines.location'];
        $relations[] = $kind === 'deliveries' ? 'lines.units' : 'lines.unit';
        if ($kind === 'returns') {
            $relations[] = 'lines.authorizations.events';
        }

        return view('worker-operations.show', ['kind' => $kind, 'label' => WorkerOperationTypes::LABELS[$kind], 'document' => $doc->load($relations)]);
    }

    public function confirm(Request $request, string $kind, int $document)
    {
        $data = $this->validated($request, ['lock_version' => 'required|integer|min:1']);
        $class = ['deliveries' => ConfirmDelivery::class, 'loans' => ConfirmLoan::class, 'returns' => ConfirmReturn::class][$kind];
        $doc = app($class)->handle($request->user(), $this->document($kind, $document), $data['lock_version']);

        return to_route('worker-operations.show', [$kind, $doc->id])->with('status', 'Operación física confirmada.');
    }

    public function cancel(Request $request, string $kind, int $document)
    {
        $data = $this->validated($request, ['lock_version' => 'required|integer|min:1', 'reason' => 'required|string|max:500']);
        $doc = app(CancelWorkerOperation::class)->handle($request->user(), $this->document($kind, $document), $data['lock_version'], $data['reason']);

        return to_route('worker-operations.show', [$kind, $doc->id])->with('status', 'Borrador cancelado y conservado.');
    }

    public function requestRegularization(Request $request, ToolReturnItem $item)
    {
        $data = $this->validated($request, ['lock_version' => 'required|integer|min:1', 'reason' => 'required|string|max:1000']);
        app(ReturnRegularization::class)->request($request->user(), $item, $data['lock_version'], $data['reason']);

        return to_route('worker-operations.show', ['returns', $item->return_id])->with('status', 'Solicitud específica registrada.');
    }

    public function decideRegularization(Request $request, ReturnRegularizationAuthorization $authorization, string $decision)
    {
        $data = $this->validated($request, ['reason' => 'required|string|max:1000']);
        app(ReturnRegularization::class)->decide($request->user(), $authorization, $decision === 'approve', $data['reason']);

        return to_route('worker-operations.show', ['returns', $authorization->item->return_id])->with('status', 'Decisión registrada.');
    }

    public function availability(Request $request)
    {
        Gate::authorize('availability.view');
        $data = $this->validated($request, ['q' => 'nullable|string|max:100', 'product_id' => 'nullable|integer|min:1', 'location_id' => 'nullable|integer|min:1']);
        $positions = StockPosition::with('product.measurementUnit', 'location')->when($data['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))->when($data['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))->when($data['q'] ?? null, fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->where(fn ($w) => $w->where('internal_code', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'))))->orderBy('product_id')->paginate(30)->withQueryString();
        $individuals = DB::table('individual_unit_positions as pos')->join('individual_units as u', 'u.id', '=', 'pos.individual_unit_id')->join('products as p', 'p.id', '=', 'pos.product_id')->leftJoin('locations as l', 'l.id', '=', 'pos.location_id')->leftJoin('individual_unit_custodies as c', 'c.individual_unit_id', '=', 'u.id')->leftJoin('tool_loan_items as li', 'li.id', '=', 'c.current_loan_item_id')->leftJoin('tool_loans as loan', 'loan.id', '=', 'li.loan_id')->leftJoin('workers as w', 'w.id', '=', 'loan.worker_id')->when($data['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('u.identifier', 'like', '%'.$term.'%')->orWhere('u.serial_number', 'like', '%'.$term.'%')->orWhere('p.internal_code', 'like', '%'.$term.'%')))->when($data['product_id'] ?? null, fn ($q, $id) => $q->where('pos.product_id', $id))->when($data['location_id'] ?? null, fn ($q, $id) => $q->where('pos.location_id', $id))->select('u.identifier', 'u.is_active', 'p.internal_code', 'pos.is_present', 'l.code', 'c.current_loan_item_id', 'loan.id as loan_id', 'w.nombres', 'w.apellidos')->orderBy('u.identifier')->paginate(30, ['*'], 'units_page')->withQueryString();
        $allLocations = Location::all()->keyBy('id');
        foreach ($positions as $position) {
            $active = true;
            $seen = [];
            $location = $position->location;
            while ($location) {
                if (isset($seen[$location->id]) || ! $location->is_active) {
                    $active = false;
                    break;
                }$seen[$location->id] = true;
                $location = $location->parent_id ? $allLocations->get($location->parent_id) : null;
            }
            $pair = DB::table('product_locations')->where('product_id', $position->product_id)->where('location_id', $position->location_id)->first();
            $ready = $active && $position->location->is_storable && $position->product->is_active && $position->product->measurementUnit->is_active && $pair?->is_active;
            $position->operational_quantity = ! $ready ? '0' : ($position->product->control_mode === 'quantity' ? $position->quantity : (string) DB::table('individual_unit_positions as pos')->join('individual_units as u', 'u.id', '=', 'pos.individual_unit_id')->leftJoin('individual_unit_custodies as c', 'c.individual_unit_id', '=', 'u.id')->where('pos.product_id', $position->product_id)->where('pos.location_id', $position->location_id)->where('pos.is_present', true)->where('u.is_active', true)->whereNull('c.current_loan_item_id')->count());
        }

        return view('worker-operations.availability', compact('positions', 'individuals') + ['productChoices' => Product::orderBy('internal_code')->get(), 'locationChoices' => Location::orderBy('code')->get()]);
    }
}
