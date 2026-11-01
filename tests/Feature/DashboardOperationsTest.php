<?php

namespace Tests\Feature;

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use App\Models\Worker;
use App\Services\DashboardOperations;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DashboardOperationsTest extends TestCase
{
    use CreatesInventory;

    public function test_empty_dashboard_has_real_zeroes_and_actionable_empty_states(): void
    {
        $response = $this->signIn(User::factory()->create(['role_id' => 1]))->get(route('dashboard'))
            ->assertOk()->assertSee('Panel de operaciones')->assertSee('Documentos en borrador')
            ->assertSee('Aún no hay operaciones confirmadas');
        $data = $response->viewData('operations');
        $this->assertSame(['with_stock' => 0, 'without_stock' => 0], $data['stock']);
        $this->assertSame(['pending' => 0, 'overdue' => 0, 'due_today' => 0], $data['loans']);
        $this->assertSame(0, array_sum(array_column($data['drafts'], 'count')));
    }

    public function test_stock_counts_distinct_active_products_and_drafts_never_count_as_stock(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '20');
        $this->postInventory($c, 'receipt', '30');
        $empty = $this->inventoryContext();
        $this->inventoryDraft($empty, 'receipt', '100');
        Product::factory()->create(['is_active' => false]);
        $data = app(DashboardOperations::class)->forUser($c['actor']);
        $this->assertSame(['with_stock' => 1, 'without_stock' => 1], $data['stock']);
        $this->assertSame(1, collect($data['drafts'])->firstWhere('label', 'Remitos')['count']);
        $this->postInventory($c, 'issue', '50');
        $this->assertSame(['with_stock' => 0, 'without_stock' => 2], app(DashboardOperations::class)->forUser($c['actor'])['stock']);
    }

    public function test_restricted_modules_are_neither_queried_nor_returned_and_openings_are_separate(): void
    {
        $c = $this->inventoryContext();
        $this->inventoryDraft($c, 'receipt');
        $this->inventoryDraft($c, 'opening');
        $codes = ['stock.view', 'loans.view', 'movements.view', 'remittances.view', 'deliveries.view', 'returns.view'];
        DB::table('permission_role')->where('role_id', 1)->whereIn('permission_id', Permission::whereIn('code', $codes)->pluck('id'))->update(['is_active' => false]);
        $actor = User::findOrFail($c['actor']->id);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/`(stock_positions|products|tool_loans|tool_returns|material_deliveries|inventory_movements|individual_unit_custodies)`/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });
        $data = app(DashboardOperations::class)->forUser($actor);
        $this->assertNull($data['stock']);
        $this->assertNull($data['loans']);
        $this->assertNull($data['recent']);
        $this->assertCount(1, $data['drafts']);
        $this->assertSame('Aperturas', $data['drafts'][0]['label']);
        $this->assertSame(1, $data['drafts'][0]['count']);
        $this->assertSame([], $queries);
    }

    public function test_recent_movements_are_limited_ordered_and_product_names_are_escaped(): void
    {
        $c = $this->inventoryContext();
        $c['product']->update(['name' => '<script>alert(1)</script>']);
        for ($i = 0; $i < 6; $i++) {
            $this->postInventory($c, 'receipt', '1');
        }
        $response = $this->signIn($c['actor'])->get(route('dashboard'))->assertOk();
        $rows = $response->viewData('operations')['recent'];
        $this->assertCount(5, $rows);
        $this->assertSame(DB::table('inventory_movements')->max('id'), $rows->first()->id);
        $response->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_due_dates_use_bolivia_day_and_returns_remove_current_obligations(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 02:00:00', 'UTC')); // Still October 24 in Bolivia.
        $actor = null;
        foreach (['2026-10-23', '2026-10-24', null] as $due) {
            $c = $this->inventoryContext('individual');
            $actor ??= $c['actor'];
            $c['product']->update(['is_loanable' => true]);
            $this->postInventory($c, 'receipt', '1');
            $worker = Worker::factory()->create();
            $loan = app(SaveLoan::class)->handle($actor, null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'due_date' => $due, 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
            $loan = app(ConfirmLoan::class)->handle($actor, $loan, 1);
            if ($due === '2026-10-23') {
                $overdueLoan = $loan;
                $destination = $c['location']->id;
            }
        }
        $this->assertSame(['pending' => 3, 'overdue' => 1, 'due_today' => 1], app(DashboardOperations::class)->forUser($actor)['loans']);
        $return = app(SaveReturn::class)->handle($actor, null, ['loan_id' => $overdueLoan->id, 'document_date' => '2026-10-24', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $overdueLoan->lines()->first()->id, 'location_id' => $destination]]]);
        app(ConfirmReturn::class)->handle($actor, $return, 1);
        $this->assertSame(['pending' => 2, 'overdue' => 0, 'due_today' => 1], app(DashboardOperations::class)->forUser($actor)['loans']);
    }
}
