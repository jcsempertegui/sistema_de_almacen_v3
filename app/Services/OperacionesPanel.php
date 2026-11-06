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
            $disponibles = DB::table('existencias_ubicacion')->where('cantidad', '>', 0)->select('producto_id')->distinct();
            $resumen = DB::table('productos as p')->where('p.activo', true)
                ->leftJoinSub($disponibles, 'disponibles', 'disponibles.producto_id', '=', 'p.id')
                ->selectRaw('COUNT(*) AS total, COUNT(disponibles.producto_id) AS con_stock')->first();
            $stock = ['with_stock' => (int) $resumen->con_stock, 'without_stock' => (int) $resumen->total - (int) $resumen->con_stock];
        }

        $loans = null;
        if ($gate->allows('loans.view')) {
            // The current custody pointer also accounts for compensated returns.
            $custody = DB::table('custodias_unidad_individual as custody')
                ->join('items_prestamo_herramienta as item', 'item.id', '=', 'custody.item_prestamo_actual_id')
                ->join('prestamos_herramientas as prestamo', 'prestamo.id', '=', 'item.prestamo_id')
                ->where('prestamo.estado', 'confirmed');
            $resumen = $custody->selectRaw('COUNT(*) AS pendientes, COALESCE(SUM(prestamo.fecha_limite < ?), 0) AS vencidos, COALESCE(SUM(prestamo.fecha_limite = ?), 0) AS hoy', [$today, $today])->first();
            $loans = ['pending' => (int) $resumen->pendientes,
                'overdue' => (int) $resumen->vencidos,
                'due_today' => (int) $resumen->hoy];
        }

        $drafts = [];
        $conteosInventario = null;
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
            if ($kinds !== null) {
                if ($conteosInventario === null) {
                    $permitidos = array_merge($gate->allows('remittances.view') ? ['receipt', 'issue'] : [], $gate->allows('initial_stock.view') ? ['opening'] : []);
                    $conteosInventario = DB::table($table)->where('estado', 'draft')->whereIn('tipo', $permitidos)
                        ->select('tipo')->selectRaw('COUNT(*) AS total')->groupBy('tipo')->pluck('total', 'tipo')->all();
                }
                $cantidad = array_sum(array_intersect_key($conteosInventario, array_flip($kinds)));
            } else {
                $cantidad = DB::table($table)->where('estado', 'draft')->count();
            }
            $drafts[] = ['label' => $label, 'count' => (int) $cantidad, 'url' => route($route, $parameters)];
        }

        return ['date' => CarbonImmutable::now('America/La_Paz')->locale('es')->isoFormat('dddd D [de] MMMM, YYYY'),
            'stock' => $stock, 'loans' => $loans, 'drafts' => $drafts,
            'recent' => $gate->allows('movements.view')
                ? MovimientoInventario::with(['producto:id,codigo_interno,nombre', 'ubicacion:id,codigo'])->orderByDesc('fecha_registro')->orderByDesc('id')->limit(5)->get()
                : null];
    }
}
