<?php

namespace Tests\Feature;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Permiso;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use App\Services\GraficasPanel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use CreatesInventory;

    public function test_empty_charts_show_real_zeroes_and_no_division_errors(): void
    {
        $usuario = Usuario::factory()->create(['rol_id' => 1]);
        $response = $this->signIn($usuario)->get(route('dashboard'))->assertOk()
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
        $charts = app(GraficasPanel::class)->paraUsuario($first['actor']);
        $this->assertSame(2, $charts['movements']['incoming']);
        $this->assertSame(1, $charts['movements']['outgoing']);
        $this->assertSame(3, $charts['movements']['weekly_total']);
        $this->assertSame(['date' => '2026-10-25', 'label' => '25/10', 'in' => 2, 'out' => 1], $charts['movements']['days'][6]);
        $this->signIn($first['actor'])->get(route('dashboard'))->assertOk()->assertSee('Ver datos diarios')->assertSee('activity-descripcion');
    }

    public function test_days_use_bolivia_time_instead_of_utc_midnight(): void
    {
        $c = $this->inventoryContext();
        $this->travelTo(CarbonImmutable::parse('2026-10-24 03:30:00', 'UTC'));
        $this->postInventory($c, 'receipt', '3');
        $this->travelTo(CarbonImmutable::parse('2026-10-24 04:30:00', 'UTC'));
        $this->postInventory($c, 'issue', '1');
        $this->travelTo(CarbonImmutable::parse('2026-10-25 05:00:00', 'UTC'));
        $days = collect(app(GraficasPanel::class)->paraUsuario($c['actor'])['movements']['days'])->keyBy('date');
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
        $charts = app(GraficasPanel::class)->paraUsuario($c['actor']);
        $this->assertSame(2, $charts['movements']['total']);
        $this->assertSame(1, $charts['movements']['weekly_total']);
    }

    public function test_without_permissions_the_server_does_not_query_or_render_chart_data(): void
    {
        $ids = Permiso::whereIn('codigo', ['movements.view', 'loans.view'])->pluck('id');
        DB::table('permiso_rol')->where('rol_id', 1)->whereIn('permiso_id', $ids)->update(['activo' => false]);
        $usuario = Usuario::factory()->create(['rol_id' => 1]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, '`movimientos_inventario`') || str_contains($query->sql, '`custodias_unidad_individual`')) {
                $queries[] = $query->sql;
            }
        });
        $response = $this->signIn($usuario)->get(route('dashboard'))->assertOk()->assertDontSee('El almacén en cifras');
        $this->assertNull($response->viewData('charts')['movements']);
        $this->assertNull($response->viewData('charts')['loans']);
        $this->assertSame([], $queries);
    }

    public function test_pending_tools_follow_current_custody_after_return_and_reopening(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $trabajador = Trabajador::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez']);
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        $service = app(GraficasPanel::class);
        $this->assertSame(1, $service->paraUsuario($c['actor'])['loans']['total']);
        $this->assertSame('Ana Pérez', $service->paraUsuario($c['actor'])['loans']['trabajadores'][0]['label']);
        $return = app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $return, 1);
        $this->assertSame(0, $service->paraUsuario($c['actor'])['loans']['total']);
        $corrections = app(CorreccionesInventario::class);
        $correccion = $corrections->save($c['actor'], null, ['tipo' => 'compensation', 'motivo' => 'Recepción registrada por error', 'fecha_documento' => '2026-10-20', 'tipo_origen' => 'returns', 'origen_id' => $return->id]);
        $correccion = $corrections->transition($c['actor'], $correccion, 'request', $correccion->version_bloqueo);
        $correccion = $corrections->transition(Usuario::factory()->create(['rol_id' => 1]), $correccion, 'approve', $correccion->version_bloqueo, 'Documento verificado por otra persona');
        $corrections->execute($c['actor'], $correccion, $correccion->version_bloqueo);
        $this->assertSame(1, $service->paraUsuario($c['actor'])['loans']['total']);
        $this->assertDatabaseCount('cierres_ciclo_prestamo', 1);
        $this->assertDatabaseCount('ciclos_custodia_prestamo', 2);
    }

    public function test_top_workers_keep_remaining_tools_in_an_other_group_and_escape_names(): void
    {
        $actor = null;
        for ($index = 0; $index < 6; $index++) {
            $c = $this->inventoryContext('individual');
            $actor ??= $c['actor'];
            $c['producto']->update(['prestable' => true]);
            $this->postInventory($c, 'receipt', '1');
            $trabajador = Trabajador::factory()->create(['nombres' => '<script>alert(1)</script>']);
            $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
            app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        }
        $response = $this->signIn($actor)->get(route('dashboard'))->assertOk()->assertSee('Otros trabajadores');
        $loans = $response->viewData('charts')['loans'];
        $this->assertCount(5, $loans['trabajadores']);
        $this->assertSame(6, $loans['total']);
        $this->assertSame(1, $loans['other']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->getContent());
    }
}
