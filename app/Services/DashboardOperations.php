<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardOperations
{
    public function forUser(User $user): array
    {
        $today = CarbonImmutable::now('America/La_Paz')->toDateString();
        $gate = Gate::forUser($user);
        $stock = null;
        if ($gate->allows('stock.view')) {
            // Count products, not quantities across incompatible measurement units.
            $active = DB::table('products')->where('is_active', true);
            $withStock = (clone $active)->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('stock_positions')->whereColumn('stock_positions.product_id', 'products.id')->where('quantity', '>', 0))->count();
            $stock = ['with_stock' => $withStock, 'without_stock' => $active->count() - $withStock];
        }

        $loans = null;
        if ($gate->allows('loans.view')) {
            // The current custody pointer also accounts for compensated returns.
            $custody = DB::table('individual_unit_custodies as custody')
                ->join('tool_loan_items as item', 'item.id', '=', 'custody.current_loan_item_id')
                ->join('tool_loans as loan', 'loan.id', '=', 'item.loan_id')
                ->where('loan.status', 'confirmed');
            $loans = ['pending' => (clone $custody)->count(),
                'overdue' => (clone $custody)->where('loan.due_date', '<', $today)->count(),
                'due_today' => (clone $custody)->where('loan.due_date', $today)->count()];
        }

        $drafts = [];
        foreach ([
            ['remittances.view', 'inventory_documents', 'Remitos', 'inventory-documents.index', ['status' => 'draft'], ['receipt', 'issue']],
            ['initial_stock.view', 'inventory_documents', 'Aperturas', 'inventory-documents.index', ['status' => 'draft', 'kind' => 'opening'], ['opening']],
            ['deliveries.view', 'material_deliveries', 'Entregas', 'worker-operations.index', ['kind' => 'deliveries', 'status' => 'draft'], null],
            ['loans.view', 'tool_loans', 'Préstamos', 'worker-operations.index', ['kind' => 'loans', 'status' => 'draft'], null],
            ['returns.view', 'tool_returns', 'Devoluciones', 'worker-operations.index', ['kind' => 'returns', 'status' => 'draft'], null],
        ] as [$permission, $table, $label, $route, $parameters, $kinds]) {
            if (! $gate->allows($permission)) {
                continue;
            }
            $query = DB::table($table)->where('status', 'draft');
            if ($kinds !== null) {
                $query->whereIn('kind', $kinds);
            }
            $drafts[] = ['label' => $label, 'count' => $query->count(), 'url' => route($route, $parameters)];
        }

        return ['date' => CarbonImmutable::now('America/La_Paz')->locale('es')->isoFormat('dddd D [de] MMMM, YYYY'),
            'stock' => $stock, 'loans' => $loans, 'drafts' => $drafts,
            'recent' => $gate->allows('movements.view')
                ? InventoryMovement::with(['product:id,internal_code,name', 'location:id,code'])->orderByDesc('posted_at')->orderByDesc('id')->limit(5)->get()
                : null];
    }
}
