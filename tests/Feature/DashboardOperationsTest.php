<?php

namespace Tests\Feature;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\OperacionesPanel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DashboardOperationsTest extends TestCase
{
    use CreatesInventory;

    public function test_empty_dashboard_has_real_zeroes_and_actionable_empty_states(): void
    {
        $response = $this->signIn(Usuario::factory()->create(['rol_id' => 1]))->get(route('dashboard'))
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
        Producto::factory()->create(['activo' => false]);
        $data = app(OperacionesPanel::class)->paraUsuario($c['actor']);
        $this->assertSame(['with_stock' => 1, 'without_stock' => 1], $data['stock']);
        $this->assertSame(1, collect($data['drafts'])->firstWhere('label', 'Remitos')['count']);
        $this->postInventory($c, 'issue', '50');
        $this->assertSame(['with_stock' => 0, 'without_stock' => 2], app(OperacionesPanel::class)->paraUsuario($c['actor'])['stock']);
    }

    public function test_restricted_modules_are_neither_queried_nor_returned_and_openings_are_separate(): void
    {
        $c = $this->inventoryContext();
        $this->inventoryDraft($c, 'receipt');
        $this->inventoryDraft($c, 'opening');
        $codes = ['stock.view', 'loans.view', 'movements.view', 'remittances.view', 'deliveries.view', 'returns.view'];
        DB::table('permiso_rol')->where('rol_id', 1)->whereIn('permiso_id', Permiso::whereIn('codigo', $codes)->pluck('id'))->update(['activo' => false]);
        $actor = Usuario::findOrFail($c['actor']->id);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (preg_match('/`(existencias_ubicacion|productos|prestamos_herramientas|devoluciones_herramientas|entregas_materiales|movimientos_inventario|custodias_unidad_individual)`/', $query->sql)) {
                $queries[] = $query->sql;
            }
        });
        $data = app(OperacionesPanel::class)->paraUsuario($actor);
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
        $c['producto']->update(['nombre' => '<script>alert(1)</script>']);
        for ($i = 0; $i < 6; $i++) {
            $this->postInventory($c, 'receipt', '1');
        }
        $response = $this->signIn($c['actor'])->get(route('dashboard'))->assertOk();
        $rows = $response->viewData('operations')['recent'];
        $this->assertCount(5, $rows);
        $this->assertSame(DB::table('movimientos_inventario')->max('id'), $rows->first()->id);
        $response->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_due_dates_use_bolivia_day_and_returns_remove_current_obligations(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 02:00:00', 'UTC')); // Still October 24 in Bolivia.
        $actor = null;
        foreach (['2026-10-23', '2026-10-24', null] as $due) {
            $c = $this->inventoryContext('individual');
            $actor ??= $c['actor'];
            $c['producto']->update(['prestable' => true]);
            $this->postInventory($c, 'receipt', '1');
            $trabajador = Trabajador::factory()->create();
            $prestamo = app(GuardarPrestamo::class)->handle($actor, null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'fecha_limite' => $due, 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
            $prestamo = app(ConfirmarPrestamo::class)->handle($actor, $prestamo, 1);
            if ($due === '2026-10-23') {
                $overdueLoan = $prestamo;
                $destination = $c['ubicacion']->id;
            }
        }
        $this->assertSame(['pending' => 3, 'overdue' => 1, 'due_today' => 1], app(OperacionesPanel::class)->paraUsuario($actor)['loans']);
        $return = app(GuardarDevolucion::class)->handle($actor, null, ['prestamo_id' => $overdueLoan->id, 'fecha_documento' => '2026-10-24', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $overdueLoan->lineas()->first()->id, 'ubicacion_id' => $destination]]]);
        app(ConfirmarDevolucion::class)->handle($actor, $return, 1);
        $this->assertSame(['pending' => 2, 'overdue' => 0, 'due_today' => 1], app(OperacionesPanel::class)->paraUsuario($actor)['loans']);
    }
}
