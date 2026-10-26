<?php

namespace Tests\Feature;

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Models\Permission;
use App\Models\User;
use App\Models\Worker;
use App\Services\DashboardCharts;
use App\Services\InventoryCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use CreatesInventory;

    public function test_empty_charts_show_real_zeroes_and_no_division_errors(): void
    {
        $user = User::factory()->create(['role_id' => 1]);
        $response = $this->signIn($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Sin movimientos en los últimos 7 días')->assertSee('Sin herramientas pendientes')
            ->assertSee('Ver datos del período')->assertSee('Ver datos de custodia');
        $charts = $response->viewData('charts');
        $this->assertSame(0, $charts['movements']['total']);
        $this->assertCount(7, $charts['movements']['days']);
        $this->assertSame(0, $charts['loans']['total']);
    }

    public function test_movement_charts_count_confirmed_rows_instead_of_quantities_or_drafts(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 14:00:00', 'UTC'));
        $first = $this->inventoryContext();
        $second = $this->inventoryContext();
        $this->postInventory($first, 'receipt', '5');
        $this->postInventory($first, 'issue', '2');
        $this->postInventory($second, 'receipt', '999');
        $this->inventoryDraft($first, 'receipt', '100');
        $charts = app(DashboardCharts::class)->forUser($first['actor']);
        $this->assertSame(2, $charts['movements']['incoming']);
        $this->assertSame(1, $charts['movements']['outgoing']);
        $this->assertSame(3, $charts['movements']['weekly_total']);
        $this->assertSame(['date' => '2026-10-25', 'label' => '25/10', 'in' => 2, 'out' => 1], $charts['movements']['days'][6]);
        $this->signIn($first['actor'])->get(route('dashboard'))->assertOk()->assertSee('Ver datos diarios')->assertSee('activity-description');
    }

    public function test_days_use_bolivia_time_instead_of_utc_midnight(): void
    {
        $c = $this->inventoryContext();
        $this->travelTo(CarbonImmutable::parse('2026-10-24 03:30:00', 'UTC'));
        $this->postInventory($c, 'receipt', '3');
        $this->travelTo(CarbonImmutable::parse('2026-10-24 04:30:00', 'UTC'));
        $this->postInventory($c, 'issue', '1');
        $this->travelTo(CarbonImmutable::parse('2026-10-25 05:00:00', 'UTC'));
        $days = collect(app(DashboardCharts::class)->forUser($c['actor'])['movements']['days'])->keyBy('date');
        $this->assertSame(1, $days['2026-10-23']['in']);
        $this->assertSame(0, $days['2026-10-24']['in']);
        $this->assertSame(1, $days['2026-10-24']['out']);
    }

    public function test_thirty_day_window_has_inclusive_local_start_and_excludes_future_rows(): void
    {
        $c = $this->inventoryContext();
        foreach (['2026-10-27 03:59:59', '2026-10-27 04:00:00', '2026-11-25 05:00:00', '2026-11-25 06:00:00'] as $time) {
            $this->travelTo(CarbonImmutable::parse($time, 'UTC'));
            $this->postInventory($c, 'receipt', '1');
        }
        $this->travelTo(CarbonImmutable::parse('2026-11-25 05:00:00', 'UTC'));
        $charts = app(DashboardCharts::class)->forUser($c['actor']);
        $this->assertSame(2, $charts['movements']['total']);
        $this->assertSame(1, $charts['movements']['weekly_total']);
    }

    public function test_without_permissions_the_server_does_not_query_or_render_chart_data(): void
    {
        $ids = Permission::whereIn('code', ['movements.view', 'loans.view'])->pluck('id');
        DB::table('permission_role')->where('role_id', 1)->whereIn('permission_id', $ids)->update(['is_active' => false]);
        $user = User::factory()->create(['role_id' => 1]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, '`inventory_movements`') || str_contains($query->sql, '`individual_unit_custodies`')) {
                $queries[] = $query->sql;
            }
        });
        $response = $this->signIn($user)->get(route('dashboard'))->assertOk()->assertDontSee('El almacén en cifras');
        $this->assertNull($response->viewData('charts')['movements']);
        $this->assertNull($response->viewData('charts')['loans']);
        $this->assertSame([], $queries);
    }

    public function test_pending_tools_follow_current_custody_after_return_and_reopening(): void
    {
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $worker = Worker::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
        $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
        $service = app(DashboardCharts::class);
        $this->assertSame(1, $service->forUser($c['actor'])['loans']['total']);
        $this->assertSame('Ana Pérez', $service->forUser($c['actor'])['loans']['workers'][0]['label']);
        $return = app(SaveReturn::class)->handle($c['actor'], null, ['loan_id' => $loan->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]]);
        app(ConfirmReturn::class)->handle($c['actor'], $return, 1);
        $this->assertSame(0, $service->forUser($c['actor'])['loans']['total']);
        $corrections = app(InventoryCorrections::class);
        $correction = $corrections->save($c['actor'], null, ['kind' => 'compensation', 'reason' => 'Recepción registrada por error', 'document_date' => '2026-10-20', 'source_kind' => 'returns', 'source_id' => $return->id]);
        $correction = $corrections->transition($c['actor'], $correction, 'request', $correction->lock_version);
        $correction = $corrections->transition(User::factory()->create(['role_id' => 1]), $correction, 'approve', $correction->lock_version, 'Documento verificado por otra persona');
        $corrections->execute($c['actor'], $correction, $correction->lock_version);
        $this->assertSame(1, $service->forUser($c['actor'])['loans']['total']);
        $this->assertDatabaseCount('loan_cycle_closures', 1);
        $this->assertDatabaseCount('loan_custody_cycles', 2);
    }

    public function test_top_workers_keep_remaining_tools_in_an_other_group_and_escape_names(): void
    {
        $actor = null;
        for ($index = 0; $index < 6; $index++) {
            $c = $this->inventoryContext('individual');
            $actor ??= $c['actor'];
            $c['product']->update(['is_loanable' => true]);
            $this->postInventory($c, 'receipt', '1');
            $worker = Worker::factory()->create(['nombres' => '<script>alert(1)</script>']);
            $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
            app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
        }
        $response = $this->signIn($actor)->get(route('dashboard'))->assertOk()->assertSee('Otros trabajadores');
        $loans = $response->viewData('charts')['loans'];
        $this->assertCount(5, $loans['workers']);
        $this->assertSame(6, $loans['total']);
        $this->assertSame(1, $loans['other']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
    }
}
