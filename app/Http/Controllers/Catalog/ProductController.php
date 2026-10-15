<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ProductCategoryRequest;
use App\Http\Requests\Catalog\ProductRequest;
use App\Http\Requests\Catalog\ProductSearchRequest;
use App\Http\Requests\Catalog\ProductStatusRequest;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Services\LocationHierarchy;
use App\Services\ProductAttributeValidator;
use App\Services\ProductDuplicateFinder;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(ProductSearchRequest $request)
    {
        $filters = $request->validated();
        $query = Product::with(['category', 'measurementUnit'])->orderBy('internal_code')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('internal_code', 'like', $pattern)->orWhere('name', 'like', $pattern)->orWhere('brand', 'like', $pattern)->orWhere('manufacturer_reference', 'like', $pattern));
        }
        foreach (['category_id', 'measurement_unit_id', 'control_mode'] as $field) {
            if ($filters[$field] ?? null) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($filters['status'] ?? null) {
            $query->where('is_active', $filters['status'] === 'active');
        }

        return view('catalog.products.index', ['products' => $query->paginate(20)->withQueryString(), 'categories' => Category::orderBy('name')->get(), 'units' => MeasurementUnit::orderBy('name')->get()]);
    }

    public function create(ProductCategoryRequest $request)
    {
        return $this->form(new Product, $request);
    }

    public function edit(ProductCategoryRequest $request, Product $product)
    {
        return $this->form($product, $request);
    }

    private function form(Product $product, ProductCategoryRequest $request)
    {
        $categoryId = (int) old('category_id', $request->validated('category_id') ?? $product->category_id);
        $definitions = $categoryId ? app(ProductAttributeValidator::class)->definitions($categoryId) : collect();
        $existing = $product->exists ? $product->attributeValues()->with('categoryAttribute.attribute')->get()->keyBy('category_attribute_id') : collect();
        $catalog = app(LocationHierarchy::class)->catalog();

        return view('catalog.products.form', [
            'product' => $product, 'categoryId' => $categoryId, 'definitions' => $definitions, 'existing' => $existing,
            'categories' => Category::where('is_active', true)->orWhere('id', $product->category_id)->orderBy('name')->get(),
            'units' => MeasurementUnit::where('is_active', true)->orWhere('id', $product->measurement_unit_id)->orderBy('name')->get(),
            'locations' => $catalog->where('is_active', true)->where('is_storable', true), 'paths' => app(LocationHierarchy::class)->paths($catalog),
        ]);
    }

    public function preview(ProductRequest $request, ProductAttributeValidator $validator, ProductDuplicateFinder $finder, ?Product $product = null)
    {
        return $this->attempt($request, function () use ($request, $validator, $finder, $product) {
            $data = $request->validated();
            $draft = $product ? clone $product : new Product;
            $draft->fill($data);
            $definitions = $validator->definitions($draft->category_id);
            $values = $validator->normalize($draft, $data['values'] ?? [], $definitions);
            $candidates = $finder->candidates($draft, $values, $definitions);
            $token = $finder->token($data, $values, $definitions, $candidates);
            $hasCandidates = $candidates->isNotEmpty();
            if (! $request->user()->can('viewAny', Product::class)) {
                $candidates = collect();
            }

            return view('catalog.products.preview', compact('draft', 'data', 'definitions', 'values', 'candidates', 'token', 'hasCandidates'));
        });
    }

    public function show(Product $product)
    {
        Gate::authorize('view', $product);
        $product->load(['category', 'measurementUnit', 'attributeValues.categoryAttribute.attribute.measurementUnit', 'attributeValues.revisions.creator']);

        return view('catalog.products.show', compact('product'));
    }

    public function store(ProductRequest $request, SaveProduct $action)
    {
        $product = $this->attempt($request, fn () => $action->handle($request->user(), null, $request->validated()));

        return $this->saved($request->user(), $product, 'Producto registrado.');
    }

    public function update(ProductRequest $request, Product $product, SaveProduct $action)
    {
        $product = $this->attempt($request, fn () => $action->handle($request->user(), $product, $request->validated()));

        return $this->saved($request->user(), $product, 'Producto actualizado.');
    }

    public function status(ProductStatusRequest $request, Product $product, SaveProduct $action)
    {
        $product = $action->handle($request->user(), $product, $request->validated(), statusOnly: true);

        return $this->saved($request->user(), $product, 'Estado del producto actualizado.');
    }

    private function saved($actor, Product $product, string $message)
    {
        return redirect($actor->can('view', $product) ? route('products.show', $product) : route('dashboard'))->with('status', $message);
    }

    private function attempt(ProductRequest $request, Closure $operation)
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            throw $exception->redirectTo($request->formUrl());
        }
    }
}
