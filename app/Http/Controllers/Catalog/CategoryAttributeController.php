<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveCategoryAttribute;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryAttributeRequest;
use App\Http\Requests\Catalog\CategoryAttributeStatusRequest;
use App\Http\Requests\SearchRequest;
use App\Models\CatalogAttribute;
use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryAttributeController extends Controller
{
    public function index(SearchRequest $request, Category $category): View
    {
        Gate::authorize('viewAny', CategoryAttribute::class);
        $filters = $request->validated();
        $query = $category->categoryAttributes()->with('attribute.measurementUnit')->orderBy('display_order')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->whereHas('attribute', fn ($query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        return view('catalog.category-attributes.index', ['category' => $category, 'associations' => $query->paginate(20)->withQueryString()]);
    }

    public function create(Category $category): View
    {
        Gate::authorize('create', CategoryAttribute::class);

        return $this->form($category, new CategoryAttribute);
    }

    public function edit(Category $category, CategoryAttribute $categoryAttribute): View
    {
        Gate::authorize('update', $categoryAttribute);

        return $this->form($category, $categoryAttribute);
    }

    private function form(Category $category, CategoryAttribute $association): View
    {
        $attributes = CatalogAttribute::where('is_active', true)
            ->whereDoesntHave('categoryAttributes', fn ($query) => $query->where('category_id', $category->id))
            ->with('measurementUnit')->orderBy('name')->get();

        return view('catalog.category-attributes.form', compact('category', 'association', 'attributes'));
    }

    public function store(CategoryAttributeRequest $request, Category $category, SaveCategoryAttribute $action): RedirectResponse
    {
        $action->handle($request->user(), $category, null, $request->validated());

        return redirect()->route('category-attributes.index', $category)->with('status', 'Atributo asociado a la categoría.');
    }

    public function update(CategoryAttributeRequest $request, Category $category, CategoryAttribute $categoryAttribute, SaveCategoryAttribute $action): RedirectResponse
    {
        $action->handle($request->user(), $category, $categoryAttribute, $request->validated());

        return redirect()->route('category-attributes.index', $category)->with('status', 'Configuración del atributo actualizada.');
    }

    public function status(CategoryAttributeStatusRequest $request, Category $category, CategoryAttribute $categoryAttribute, SaveCategoryAttribute $action): RedirectResponse
    {
        $action->handle($request->user(), $category, $categoryAttribute, $request->validated(), statusOnly: true);

        return redirect()->route('category-attributes.index', $category)->with('status', 'Estado de la asociación actualizado.');
    }
}
