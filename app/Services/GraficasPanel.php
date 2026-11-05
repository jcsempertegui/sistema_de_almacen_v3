<?php

namespace App\Services;

use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class GraficasPanel
{
    public function paraUsuario(Usuario $usuario): array
    {
        $now = CarbonImmutable::now('America/La_Paz');
        $gate = Gate::forUser($usuario);

        return [
            'as_of' => $now->format('d/m/Y H:i'),
            'movements' => $gate->allows('movements.view') ? $this->movements($now) : null,
            'loans' => $gate->allows('loans.view') ? $this->loans() : null,
        ];
    }

    private function movements(CarbonImmutable $now): array
    {
        // Counts ledger rows, never sums quantities with incompatible unidades.
        $rows = DB::table('movimientos_inventario')
            ->selectRaw('DATE(CONVERT_TZ(fecha_registro, ?, ?)) AS movement_day, direccion, COUNT(*) AS total', ['+00:00', $now->format('P')])
            ->where('fecha_registro', '>=', $now->startOfDay()->subDays(29)->utc()->format('Y-m-d H:i:s'))
            ->where('fecha_registro', '<=', $now->utc()->format('Y-m-d H:i:s'))
            ->groupBy('movement_day', 'direccion')->get();
        $totals = ['in' => 0, 'out' => 0];
        $days = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $now->subDays($offset);
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'label' => $day->format('d/m'), 'in' => 0, 'out' => 0];
        }
        foreach ($rows as $row) {
            $totals[$row->direccion] += (int) $row->total;
            if (isset($days[$row->movement_day])) {
                $days[$row->movement_day][$row->direccion] = (int) $row->total;
            }
        }

        return ['days' => array_values($days), 'incoming' => $totals['in'], 'outgoing' => $totals['out'], 'total' => array_sum($totals), 'weekly_total' => array_sum(array_column($days, 'in')) + array_sum(array_column($days, 'out'))];
    }

    private function loans(): array
    {
        // Current custody is authoritative, including reopened return compensations.
        $groups = DB::table('custodias_unidad_individual as custody')
            ->join('items_prestamo_herramienta as item', 'item.id', '=', 'custody.item_prestamo_actual_id')
            ->join('prestamos_herramientas as prestamo', 'prestamo.id', '=', 'item.prestamo_id')
            ->join('trabajadores as trabajador', 'trabajador.id', '=', 'prestamo.trabajador_id')
            ->where('prestamo.estado', 'confirmed')
            ->select('trabajador.id', 'trabajador.codigo', 'trabajador.nombres', 'trabajador.apellidos')->selectRaw('COUNT(*) AS total')
            ->groupBy('trabajador.id', 'trabajador.codigo', 'trabajador.nombres', 'trabajador.apellidos')
            ->orderByDesc('total')->orderBy('trabajador.id')->get();

        return [
            'total' => (int) $groups->sum('total'),
            'trabajadores' => $groups->take(5)->map(fn ($trabajador) => ['label' => trim($trabajador->nombres.' '.$trabajador->apellidos), 'codigo' => $trabajador->codigo, 'total' => (int) $trabajador->total])->all(),
            'other' => (int) $groups->skip(5)->sum('total'),
        ];
    }
}
