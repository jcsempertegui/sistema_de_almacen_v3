<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveCatalogAttribute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\AttributeSearchRequest;
use App\Http\Requests\Catalog\CatalogAttributeRequest;
use App\Http\Requests\Catalog\CatalogAttributeStatusRequest;
use App\Models\CatalogAttribute;
use App\Models\MeasurementUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CatalogAttributeController extends Controller
{
    public function index(AttributeSearchRequest $request): View
    {
        Gate::authorize('viewAny', CatalogAttribute::class);
        $filters = $request->validated();
        $query = CatalogAttribute::with('measurementUnit')->orderBy('name')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        if ($type = $filters['data_type'] ?? null) {
            $query->where('data_type', $type);
        }

        return view('catalog.attributes.index', ['attributes' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', CatalogAttribute::class);

        return $this->form(new CatalogAttribute);
    }

    public function edit(CatalogAttribute $catalogAttribute): View
    {
        Gate::authorize('update', $catalogAttribute);

        return $this->form($catalogAttribute);
    }

    private function form(CatalogAttribute $attribute): View
    {
        $units = MeasurementUnit::where('is_active', true)->orWhere('id', $attribute->measurement_unit_id)->orderBy('name')->get();

        return view('catalog.attributes.form', compact('attribute', 'units'));
    }

    public function store(CatalogAttributeRequest $request, SaveCatalogAttribute $action): RedirectResponse
    {
        $action->handle($request->user(), null, $request->validated());

        return redirect()->route('attributes.index')->with('status', 'Atributo técnico registrado.');
    }

    public function update(CatalogAttributeRequest $request, CatalogAttribute $catalogAttribute, SaveCatalogAttribute $action): RedirectResponse
    {
        $action->handle($request->user(), $catalogAttribute, $request->validated());

        return redirect()->route('attributes.index')->with('status', 'Atributo técnico actualizado.');
    }

    public function status(CatalogAttributeStatusRequest $request, CatalogAttribute $catalogAttribute, SaveCatalogAttribute $action): RedirectResponse
    {
        $action->handle($request->user(), $catalogAttribute, $request->validated(), statusOnly: true);

        return redirect()->route('attributes.index')->with('status', 'Estado del atributo actualizado.');
    }
}
