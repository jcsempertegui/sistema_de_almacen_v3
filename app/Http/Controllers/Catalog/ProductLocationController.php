<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveProductLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductLocationRequest;
use App\Http\Requests\Catalog\ProductLocationSearchRequest;
use App\Http\Requests\Catalog\ProductLocationStatusRequest;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Services\LocationHierarchy;
use Illuminate\Support\Facades\Gate;

class ProductLocationController extends Controller
{
    public function index(ProductLocationSearchRequest $request, Product $product, LocationHierarchy $hierarchy)
    {
        $query = $product->productLocations()->with('location')->orderBy('id');
        $filters = $request->validated();
        if ($filters['status'] ?? null) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('location', fn ($q) => $q->where(fn ($q) => $q->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern)));
        }

        return view('catalog.product-locations.index', ['product' => $product, 'links' => $query->paginate(20)->withQueryString(), 'paths' => $hierarchy->paths($hierarchy->catalog())]);
    }

    public function create(Product $product, LocationHierarchy $hierarchy)
    {
        Gate::authorize('create', ProductLocation::class);
        $catalog = $hierarchy->catalog();

        return view('catalog.product-locations.form', ['product' => $product, 'locations' => $catalog->where('is_active', true)->where('is_storable', true), 'paths' => $hierarchy->paths($catalog)]);
    }

    public function store(ProductLocationRequest $request, Product $product, SaveProductLocation $action)
    {
        $action->handle($request->user(), $product, null, $request->validated());

        return $this->saved($request->user(), $product);
    }

    public function status(ProductLocationStatusRequest $request, Product $product, ProductLocation $productLocation, SaveProductLocation $action)
    {
        $action->handle($request->user(), $product, $productLocation, $request->validated());

        return $this->saved($request->user(), $product);
    }

    private function saved($actor, Product $product)
    {
        return redirect($actor->can('viewAny', ProductLocation::class) ? route('product-locations.index', $product) : route('dashboard'))->with('status', 'Asociación de ubicación guardada.');
    }
}
