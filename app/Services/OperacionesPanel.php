<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OperacionesPanel
{
    public function paraUsuario(Usuario $usuario): array
    {
        $today = CarbonImmutable::now('America/La_Paz')->toDateString();
        $gate = Gate::forUser($usuario);
        $stock = null;
        if ($gate->allows('stock.view')) {
            // Count productos, not quantities across incompatible measurement unidades.
            $active = DB::table('productos')->where('activo', true);
            $withStock = (clone $active)->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('existencias_ubicacion')->whereColumn('existencias_ubicacion.producto_id', 'productos.id')->where('cantidad', '>', 0))->count();
            $stock = ['with_stock' => $withStock, 'without_stock' => $active->count() - $withStock];
        }

        $loans = null;
        if ($gate->allows('loans.view')) {
            // The current custody pointer also accounts for compensated returns.
            $custody = DB::table('custodias_unidad_individual as custody')
                ->join('items_prestamo_herramienta as item', 'item.id', '=', 'custody.item_prestamo_actual_id')
                ->join('prestamos_herramientas as prestamo', 'prestamo.id', '=', 'item.prestamo_id')
                ->where('prestamo.estado', 'confirmed');
            $loans = ['pending' => (clone $custody)->count(),
                'overdue' => (clone $custody)->where('prestamo.fecha_limite', '<', $today)->count(),
                'due_today' => (clone $custody)->where('prestamo.fecha_limite', $today)->count()];
        }

        $drafts = [];
        foreach ([
            ['remittances.view', 'documentos_inventario', 'Remitos', 'documentos-inventario.index', ['estado' => 'draft'], ['receipt', 'issue']],
            ['initial_stock.view', 'documentos_inventario', 'Aperturas', 'documentos-inventario.index', ['estado' => 'draft', 'tipo' => 'opening'], ['opening']],
            ['deliveries.view', 'entregas_materiales', 'Entregas', 'operaciones-trabajadores.index', ['clase' => 'deliveries', 'estado' => 'draft'], null],
            ['loans.view', 'prestamos_herramientas', 'Préstamos', 'operaciones-trabajadores.index', ['clase' => 'loans', 'estado' => 'draft'], null],
            ['returns.view', 'devoluciones_herramientas', 'Devoluciones', 'operaciones-trabajadores.index', ['clase' => 'returns', 'estado' => 'draft'], null],
        ] as [$permiso, $table, $label, $route, $parameters, $kinds]) {
            if (! $gate->allows($permiso)) {
                continue;
            }
            $query = DB::table($table)->where('estado', 'draft');
            if ($kinds !== null) {
                $query->whereIn('tipo', $kinds);
            }
            $drafts[] = ['label' => $label, 'count' => $query->count(), 'url' => route($route, $parameters)];
        }

        return ['date' => CarbonImmutable::now('America/La_Paz')->locale('es')->isoFormat('dddd D [de] MMMM, YYYY'),
            'stock' => $stock, 'loans' => $loans, 'drafts' => $drafts,
            'recent' => $gate->allows('movements.view')
                ? MovimientoInventario::with(['producto:id,codigo_interno,nombre', 'ubicacion:id,codigo'])->orderByDesc('fecha_registro')->orderByDesc('id')->limit(5)->get()
                : null];
    }
}
