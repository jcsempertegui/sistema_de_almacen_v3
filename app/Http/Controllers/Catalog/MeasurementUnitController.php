<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveMeasurementUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\MeasurementUnitRequest;
use App\Http\Requests\Catalog\MeasurementUnitStatusRequest;
use App\Http\Requests\SearchRequest;
use App\Models\MeasurementUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MeasurementUnitController extends Controller
{
    public function index(SearchRequest $request): View
    {
        Gate::authorize('viewAny', MeasurementUnit::class);
        $filters = $request->validated();
        $query = MeasurementUnit::orderBy('name')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern)->orWhere('symbol', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        return view('catalog.measurement-units.index', ['units' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', MeasurementUnit::class);

        return view('catalog.measurement-units.form', ['unit' => new MeasurementUnit]);
    }

    public function edit(MeasurementUnit $measurementUnit): View
    {
        Gate::authorize('update', $measurementUnit);

        return view('catalog.measurement-units.form', ['unit' => $measurementUnit]);
    }

    public function store(MeasurementUnitRequest $request, SaveMeasurementUnit $action): RedirectResponse
    {
        $action->handle($request->user(), null, $request->validated());

        return redirect()->route('measurement-units.index')->with('status', 'Unidad de medida registrada.');
    }

    public function update(MeasurementUnitRequest $request, MeasurementUnit $measurementUnit, SaveMeasurementUnit $action): RedirectResponse
    {
        $action->handle($request->user(), $measurementUnit, $request->validated());

        return redirect()->route('measurement-units.index')->with('status', 'Unidad de medida actualizada.');
    }

    public function status(MeasurementUnitStatusRequest $request, MeasurementUnit $measurementUnit, SaveMeasurementUnit $action): RedirectResponse
    {
        $action->handle($request->user(), $measurementUnit, $request->validated(), statusOnly: true);

        return redirect()->route('measurement-units.index')->with('status', 'Estado de la unidad de medida actualizado.');
    }
}
