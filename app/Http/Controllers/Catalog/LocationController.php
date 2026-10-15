<?php

namespace App\Http\Controllers\Catalog;

use App\Actions\Catalog\SaveLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\LocationRequest;
use App\Http\Requests\Catalog\LocationSearchRequest;
use App\Http\Requests\Catalog\LocationStatusRequest;
use App\Models\Location;
use App\Services\LocationHierarchy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(LocationSearchRequest $request, LocationHierarchy $hierarchy): View
    {
        Gate::authorize('viewAny', Location::class);
        $filters = $request->validated();
        $query = Location::orderBy('code')->orderBy('id');
        if ($search = $filters['q'] ?? null) {
            $pattern = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($query) => $query->where('code', 'like', $pattern)->orWhere('name', 'like', $pattern));
        }
        if ($status = $filters['status'] ?? null) {
            $query->where('is_active', $status === 'active');
        }
        if ($type = $filters['type'] ?? null) {
            $query->where('type', $type);
        }
        if ($filters['roots_only'] ?? false) {
            $query->whereNull('parent_id');
        } elseif ($parent = $filters['parent_id'] ?? null) {
            $query->where('parent_id', $parent);
        }
        $data = DB::transaction(function () use ($hierarchy, $query) {
            // Keep the page, counts and ancestry in the same REPEATABLE-READ
            // snapshot when a location is created or reparented concurrently.
            $catalog = $hierarchy->catalog();

            return ['locations' => $query->paginate(20)->withQueryString(), 'parents' => $catalog->whereIn('id', $catalog->pluck('parent_id')->filter()->unique()), 'paths' => $hierarchy->paths($catalog)];
        });

        return view('catalog.locations.index', $data);
    }

    public function tree(LocationHierarchy $hierarchy): View
    {
        Gate::authorize('viewAny', Location::class);
        $catalog = $hierarchy->catalog();

        return view('catalog.locations.tree', ['rows' => $hierarchy->treeRows($catalog), 'paths' => $hierarchy->paths($catalog)]);
    }

    public function create(LocationHierarchy $hierarchy): View
    {
        Gate::authorize('create', Location::class);

        return $this->form(new Location, $hierarchy);
    }

    public function edit(Location $location, LocationHierarchy $hierarchy): View
    {
        Gate::authorize('update', $location);

        return $this->form($location, $hierarchy);
    }

    private function form(Location $location, LocationHierarchy $hierarchy): View
    {
        $catalog = $hierarchy->catalog();
        $excluded = $location->exists ? $hierarchy->subtreeIds($location->id) : [];
        $parents = $catalog->where('is_active', true)->whereNotIn('id', $excluded);
        $paths = $hierarchy->paths($catalog);

        return view('catalog.locations.form', compact('location', 'parents', 'paths'));
    }

    public function store(LocationRequest $request, SaveLocation $action): RedirectResponse
    {
        $action->handle($request->user(), null, $request->validated());

        return redirect()->route('locations.index')->with('status', 'Ubicación registrada.');
    }

    public function update(LocationRequest $request, Location $location, SaveLocation $action): RedirectResponse
    {
        $action->handle($request->user(), $location, $request->validated());

        return redirect()->route('locations.index')->with('status', 'Ubicación actualizada.');
    }

    public function status(LocationStatusRequest $request, Location $location, SaveLocation $action): RedirectResponse
    {
        $action->handle($request->user(), $location, $request->validated(), statusOnly: true);

        return redirect()->route('locations.index')->with('status', 'Estado de la ubicación actualizado.');
    }
}
