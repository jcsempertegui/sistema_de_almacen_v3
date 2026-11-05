<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\ExistenciaUbicacion;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Ubicacion;
use App\Support\ValidacionInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ControladorConsultaInventario extends Controller
{
    public function stock(Request $request)
    {
        Gate::authorize('stock.view');
        $filters = $this->filters($request);
        $positions = $this->apply(ExistenciaUbicacion::with('producto', 'ubicacion'), $filters)->orderBy('producto_id')->orderBy('ubicacion_id')->paginate(30)->withQueryString();
        $individuals = DB::table('posiciones_unidad_individual as pos')->join('unidades_individuales as u', 'u.id', '=', 'pos.unidad_individual_id')->join('productos as p', 'p.id', '=', 'pos.producto_id')->leftJoin('ubicaciones as l', 'l.id', '=', 'pos.ubicacion_id')
            ->when($filters['producto_id'] ?? null, fn ($q, $id) => $q->where('pos.producto_id', $id))
            ->when($filters['ubicacion_id'] ?? null, fn ($q, $id) => $q->where('pos.ubicacion_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('u.identificador', 'like', '%'.$term.'%')->orWhere('p.codigo_interno', 'like', '%'.$term.'%')->orWhere('p.nombre', 'like', '%'.$term.'%')))
            ->select('u.identificador', 'p.codigo_interno', 'pos.*', 'l.codigo as location_code')->orderBy('u.identificador')->paginate(30, ['*'], 'units_page')->withQueryString();

        return view('inventario.stock', compact('positions', 'individuals') + $this->choices());
    }

    public function movements(Request $request)
    {
        Gate::authorize('movements.view');
        $filters = $this->filters($request);
        $movements = $this->apply(MovimientoInventario::with('producto', 'ubicacion', 'line.unidades', 'line.documento', 'deliveryLine.unidades', 'deliveryLine.documento', 'itemPrestamo.unidad', 'itemPrestamo.documento', 'returnItem.unidad', 'returnItem.documento', 'returnItem.itemPrestamo'), $filters)->latest('id')->paginate(30)->withQueryString();

        return view('inventario.movements', compact('movements') + $this->choices());
    }

    private function filters(Request $request): array
    {
        return ValidacionInventario::validate($request, ['producto_id' => ['nullable', 'integer', 'min:1'], 'ubicacion_id' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:100']]);
    }

    private function choices(): array
    {
        return ['productos' => Producto::orderBy('codigo_interno')->get(['id', 'codigo_interno', 'nombre']), 'ubicaciones' => Ubicacion::orderBy('codigo')->get(['id', 'codigo', 'nombre'])];
    }

    private function apply($query, array $filters)
    {
        return $query->when($filters['producto_id'] ?? null, fn ($q, $id) => $q->where('producto_id', $id))
            ->when($filters['ubicacion_id'] ?? null, fn ($q, $id) => $q->where('ubicacion_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->whereHas('producto', fn ($p) => $p->where(fn ($w) => $w->where('codigo_interno', 'like', '%'.$term.'%')->orWhere('nombre', 'like', '%'.$term.'%'))));
    }
}
