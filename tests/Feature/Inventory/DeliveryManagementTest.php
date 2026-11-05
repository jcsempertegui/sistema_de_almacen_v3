<?php

namespace Tests\Feature\Inventory;

use App\Actions\Entregas\ConfirmarEntrega;
use App\Actions\Entregas\GuardarEntrega;
use App\Models\Trabajador;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DeliveryManagementTest extends TestCase
{
    use CreatesInventory;

    private function data(array $c, string $cantidad = '2'): array
    {
        return ['trabajador_id' => Trabajador::factory()->create()->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad' => $cantidad, 'unit_ids' => $c['unidad'] ? [$c['unidad']->id] : []]]];
    }

    public function test_delivery_draft_edit_confirm_retry_and_historical_worker(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $data = $this->data($c);
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, $data);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $data['version_bloqueo'] = 1;
        $data['lineas'][0]['cantidad'] = '3';
        $doc = app(GuardarEntrega::class)->handle($c['actor'], $doc, $data);
        $this->assertSame(2, $doc->version_bloqueo);
        $doc = app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 2);
        app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '2.000000']);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $snapshot = $doc->datos_historicos_trabajador;
        Trabajador::findOrFail($data['trabajador_id'])->update(['nombres' => 'Otro']);
        $this->assertSame($snapshot, $doc->fresh()->datos_historicos_trabajador);
    }

    public function test_individual_delivery_records_only_one_physical_outgoing_and_source(): void
    {
        $c = $this->inventoryContext('individual');
        $this->postInventory($c, 'receipt', '1');
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, $this->data($c, '1'));
        app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseHas('posiciones_unidad_individual', ['unidad_individual_id' => $c['unidad']->id, 'presente' => 0, 'ultima_linea_documento_id' => null, 'ultima_linea_entrega_id' => $doc->lineas()->first()->id]);
        $this->assertDatabaseCount('documentos_inventario', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_insufficient_stock_rolls_back_and_loanables_are_rejected(): void
    {
        $c = $this->inventoryContext();
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, $this->data($c));
        try {
            app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
            $this->fail('Missing stock accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('existencias_ubicacion', 0);
        }
        $this->assertSame('draft', $doc->fresh()->estado);
        $i = $this->inventoryContext('individual');
        $i['producto']->update(['prestable' => true]);
        $this->expectException(ValidationException::class);
        app(GuardarEntrega::class)->handle($i['actor'], null, $this->data($i, '1'));
    }

    public function test_failure_after_posting_rolls_back_stock_and_movement(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, $this->data($c));
        Event::listen('worker_operation.line_posted', fn () => throw new \RuntimeException('Injected fallo'));
        try {
            app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expected fallo');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected fallo', $e->getMessage());
        }
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '5.000000']);
        $this->assertSame('draft', $doc->fresh()->estado);
    }

    public function test_revoked_permission_is_checked_inside_confirmation(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '5');
        $doc = app(GuardarEntrega::class)->handle($c['actor'], null, $this->data($c));
        $id = DB::table('permisos')->where('codigo', 'deliveries.confirm')->value('id');
        DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', $id)->update(['activo' => false]);
        $this->expectException(AuthorizationException::class);
        app(ConfirmarEntrega::class)->handle($c['actor'], $doc, 1);
    }
}
