<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryRequest;
use App\Http\Requests\Catalog\CategoryStatusRequest;
use App\Http\Requests\SearchRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(SearchRequest $request): View
    {
        Gate::authorize('viewAny', Category::class);
        $filters = $request->validated();
        $query = Category::orderBy('name')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }

        return view('catalog.categories.index', ['categories' => $query->paginate(20)->withQueryString()]);
    }

    public function create(): View
    {
        Gate::authorize('create', Category::class);

        return view('catalog.categories.form', ['category' => new Category]);
    }

    public function edit(Category $category): View
    {
        Gate::authorize('update', $category);

        return view('catalog.categories.form', ['category' => $category]);
    }

    public function store(CategoryRequest $request, SaveCategory $action): RedirectResponse
    {
        $action->handle($request->user(), null, $request->validated());

        return redirect()->route('categories.index')->with('status', 'Categoría registrada.');
    }

    public function update(CategoryRequest $request, Category $category, SaveCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $category, $request->validated());

        return redirect()->route('categories.index')->with('status', 'Categoría actualizada.');
    }

    public function status(CategoryStatusRequest $request, Category $category, SaveCategory $action): RedirectResponse
    {
        $action->handle($request->user(), $category, $request->validated(), statusOnly: true);

        return redirect()->route('categories.index')->with('status', 'Estado de la categoría actualizado.');
    }
}
