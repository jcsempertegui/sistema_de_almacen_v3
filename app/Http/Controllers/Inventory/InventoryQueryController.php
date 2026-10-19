<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockPosition;
use App\Support\InventoryValidation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class InventoryQueryController extends Controller
{
    public function stock(Request $request)
    {
        Gate::authorize('stock.view');
        $filters = $this->filters($request);
        $positions = $this->apply(StockPosition::with('product', 'location'), $filters)->orderBy('product_id')->orderBy('location_id')->paginate(30)->withQueryString();
        $individuals = DB::table('individual_unit_positions as pos')->join('individual_units as u', 'u.id', '=', 'pos.individual_unit_id')->join('products as p', 'p.id', '=', 'pos.product_id')->leftJoin('locations as l', 'l.id', '=', 'pos.location_id')
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('pos.product_id', $id))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('pos.location_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('u.identifier', 'like', '%'.$term.'%')->orWhere('p.internal_code', 'like', '%'.$term.'%')->orWhere('p.name', 'like', '%'.$term.'%')))
            ->select('u.identifier', 'p.internal_code', 'pos.is_present', 'l.code as location_code', 'pos.last_document_line_id')->orderBy('u.identifier')->paginate(30, ['*'], 'units_page')->withQueryString();

        return view('inventory.stock', compact('positions', 'individuals') + $this->choices());
    }

    public function movements(Request $request)
    {
        Gate::authorize('movements.view');
        $filters = $this->filters($request);
        $movements = $this->apply(InventoryMovement::with('product', 'location', 'line.units', 'line.document'), $filters)->latest('id')->paginate(30)->withQueryString();

        return view('inventory.movements', compact('movements') + $this->choices());
    }

    private function filters(Request $request): array
    {
        return InventoryValidation::validate($request, ['product_id' => ['nullable', 'integer', 'min:1'], 'location_id' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:100']]);
    }

    private function choices(): array
    {
        return ['products' => Product::orderBy('internal_code')->get(['id', 'internal_code', 'name']), 'locations' => Location::orderBy('code')->get(['id', 'code', 'name'])];
    }

    private function apply($query, array $filters)
    {
        return $query->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('location_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('product', fn ($p) => $p->where(fn ($w) => $w->where('internal_code', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'))));
    }
}
