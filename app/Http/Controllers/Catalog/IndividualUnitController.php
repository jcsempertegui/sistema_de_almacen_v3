<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveIndividualUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\IndividualUnitRequest;
use App\Http\Requests\Catalog\IndividualUnitSearchRequest;
use App\Http\Requests\Catalog\IndividualUnitStatusRequest;
use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Services\LocationHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IndividualUnitController extends Controller
{
    public function index(IndividualUnitSearchRequest $request, LocationHierarchy $hierarchy): View
    {
        $filters = $request->validated();
        $query = IndividualUnit::with(['product', 'location'])->orderBy('identifier')->orderBy('id');
        if (($filters['q'] ?? null) !== null && $filters['q'] !== '') {
            $search = $filters['q'];
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('identifier', 'like', $pattern)->orWhere('serial_number', 'like', $pattern));
        }
        foreach (['product_id', 'location_id'] as $key) {
            if ($filters[$key] ?? null) {
                $query->where($key, $filters[$key]);
            }
        }
        if ($filters['status'] ?? null) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return view('catalog.individual-units.index', ['units' => $query->paginate(20)->withQueryString(), 'products' => Product::where('control_mode', 'individual')->orderBy('internal_code')->get(), 'locations' => Location::orderBy('code')->get(), 'paths' => $hierarchy->paths($hierarchy->catalog())]);
    }

    public function create(Request $request, LocationHierarchy $hierarchy): View
    {
        Gate::authorize('create', IndividualUnit::class);
        $filters = $request->validate(['product_id' => ['nullable', 'integer', 'exists:products,id']]);
        $unit = new IndividualUnit(['product_id' => $filters['product_id'] ?? null]);

        return $this->form($unit, $hierarchy);
    }

    public function edit(IndividualUnit $individualUnit, LocationHierarchy $hierarchy): View
    {
        Gate::authorize('update', $individualUnit);

        return $this->form($individualUnit, $hierarchy);
    }

    private function form(IndividualUnit $unit, LocationHierarchy $hierarchy): View
    {
        $products = Product::where('is_active', true)->where('control_mode', 'individual')->orderBy('internal_code')->get();
        $links = ProductLocation::with(['location', 'product'])->where('is_active', true)->whereHas('product', fn ($q) => $q->where('is_active', true)->where('control_mode', 'individual'))->whereHas('location', fn ($q) => $q->where('is_active', true)->where('is_storable', true))->get();
        $paths = $hierarchy->paths($hierarchy->catalog());
        $unit->load(['product', 'location']);

        return view('catalog.individual-units.form', compact('unit', 'products', 'links', 'paths'));
    }

    public function show(IndividualUnit $individualUnit, LocationHierarchy $hierarchy): View
    {
        Gate::authorize('view', $individualUnit);

        return view('catalog.individual-units.show', ['unit' => $individualUnit->load(['product', 'location', 'creator', 'updater']), 'paths' => $hierarchy->paths($hierarchy->catalog())]);
    }

    public function store(IndividualUnitRequest $request, SaveIndividualUnit $action): RedirectResponse
    {
        $unit = $action->handle($request->user(), null, $request->validated());

        return redirect()->route($request->user()->can('view', $unit) ? 'individual-units.show' : 'dashboard', $request->user()->can('view', $unit) ? [$unit] : [])->with('status', 'Unidad individualizada registrada.');
    }

    public function update(IndividualUnitRequest $request, IndividualUnit $individualUnit, SaveIndividualUnit $action): RedirectResponse
    {
        $unit = $action->handle($request->user(), $individualUnit, $request->validated());

        return redirect()->route($request->user()->can('view', $unit) ? 'individual-units.show' : 'dashboard', $request->user()->can('view', $unit) ? [$unit] : [])->with('status', 'Unidad individualizada actualizada.');
    }

    public function status(IndividualUnitStatusRequest $request, IndividualUnit $individualUnit, SaveIndividualUnit $action): RedirectResponse
    {
        $unit = $action->handle($request->user(), $individualUnit, $request->validated(), statusOnly: true);

        return redirect()->route($request->user()->can('view', $unit) ? 'individual-units.show' : 'dashboard', $request->user()->can('view', $unit) ? [$unit] : [])->with('status', 'Estado de la unidad actualizado.');
    }
}
