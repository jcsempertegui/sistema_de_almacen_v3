<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardCharts
{
    public function forUser(User $user): array
    {
        $now = CarbonImmutable::now('America/La_Paz');
        $gate = Gate::forUser($user);

        return [
            'as_of' => $now->format('d/m/Y H:i'),
            'movements' => $gate->allows('movements.view') ? $this->movements($now) : null,
            'loans' => $gate->allows('loans.view') ? $this->loans() : null,
        ];
    }

    private function movements(CarbonImmutable $now): array
    {
        // Counts ledger rows, never sums quantities with incompatible units.
        $rows = DB::table('inventory_movements')
            ->selectRaw('DATE(CONVERT_TZ(posted_at, ?, ?)) AS movement_day, direction, COUNT(*) AS total', ['+00:00', $now->format('P')])
            ->where('posted_at', '>=', $now->startOfDay()->subDays(29)->utc()->format('Y-m-d H:i:s'))
            ->where('posted_at', '<=', $now->utc()->format('Y-m-d H:i:s'))
            ->groupBy('movement_day', 'direction')->get();
        $totals = ['in' => 0, 'out' => 0];
        $days = [];
        for ($offset = 6; $offset >= 0; $offset--) {
            $day = $now->subDays($offset);
            $days[$day->toDateString()] = ['date' => $day->toDateString(), 'label' => $day->format('d/m'), 'in' => 0, 'out' => 0];
        }
        foreach ($rows as $row) {
            $totals[$row->direction] += (int) $row->total;
            if (isset($days[$row->movement_day])) {
                $days[$row->movement_day][$row->direction] = (int) $row->total;
            }
        }

        return ['days' => array_values($days), 'incoming' => $totals['in'], 'outgoing' => $totals['out'], 'total' => array_sum($totals), 'weekly_total' => array_sum(array_column($days, 'in')) + array_sum(array_column($days, 'out'))];
    }

    private function loans(): array
    {
        // Current custody is authoritative, including reopened return compensations.
        $groups = DB::table('individual_unit_custodies as custody')
            ->join('tool_loan_items as item', 'item.id', '=', 'custody.current_loan_item_id')
            ->join('tool_loans as loan', 'loan.id', '=', 'item.loan_id')
            ->join('workers as worker', 'worker.id', '=', 'loan.worker_id')
            ->where('loan.status', 'confirmed')
            ->select('worker.id', 'worker.codigo', 'worker.nombres', 'worker.apellidos')->selectRaw('COUNT(*) AS total')
            ->groupBy('worker.id', 'worker.codigo', 'worker.nombres', 'worker.apellidos')
            ->orderByDesc('total')->orderBy('worker.id')->get();

        return [
            'total' => (int) $groups->sum('total'),
            'workers' => $groups->take(5)->map(fn ($worker) => ['label' => trim($worker->nombres.' '.$worker->apellidos), 'code' => $worker->codigo, 'total' => (int) $worker->total])->all(),
            'other' => (int) $groups->skip(5)->sum('total'),
        ];
    }
}
