<?php

namespace Tests\Feature\Inventory;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\DevolucionHerramienta;
use App\Models\Trabajador;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Services\RegularizacionDevolucion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class ReturnManagementTest extends TestCase
{
    use CreatesInventory;

    private function prestamo(): array
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $other = UnidadIndividual::factory()->create(['producto_id' => $c['producto']->id, 'ubicacion_id' => null]);
        $data = $this->inventoryData($c, 'receipt', '2');
        $data['lineas'][0]['unit_ids'][] = $other->id;
        $receipt = app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $data);
        app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $receipt, 1);
        $trabajador = Trabajador::factory()->create();
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 2, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id], ['unidad_individual_id' => $other->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);

        return $c + compact('prestamo', 'trabajador', 'other');
    }

    private function draft(array $c, int $item, ?int $ubicacion = null): DevolucionHerramienta
    {
        return app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $c['prestamo']->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $item, 'ubicacion_id' => $ubicacion ?? $c['ubicacion']->id]]]);
    }

    public function test_partial_then_final_return_with_inactive_worker_and_different_destination(): void
    {
        $c = $this->prestamo();
        $c['trabajador']->update(['activo' => false]);
        $items = $c['prestamo']->lineas()->get();
        $ubicacion = Ubicacion::factory()->create();
        UbicacionProducto::factory()->create(['producto_id' => $c['producto']->id, 'ubicacion_id' => $ubicacion->id]);
        $doc = $this->draft($c, $items[0]->id, $ubicacion->id);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
        $this->assertSame('partial', $c['prestamo']->fresh()->estado_responsabilidad);
        $this->assertDatabaseCount('cierres_item_prestamo', 1);
        $this->assertDatabaseHas('posiciones_unidad_individual', ['unidad_individual_id' => $items[0]->unidad_individual_id, 'presente' => 1, 'ubicacion_id' => $ubicacion->id, 'ultimo_item_prestamo_id' => null, 'ultimo_item_devolucion_id' => $doc->lineas()->first()->id]);
        $this->assertNull(UnidadIndividual::findOrFail($items[0]->unidad_individual_id)->ubicacion_id);
        $last = $this->draft($c, $items[1]->id);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $last, 1);
        $this->assertSame('returned', $c['prestamo']->fresh()->estado_responsabilidad);
        $this->assertDatabaseCount('movimientos_inventario', 5);
        $this->assertSame($c['prestamo']->datos_historicos_trabajador, $last->fresh()->datos_historicos_trabajador);
        $physical = $c;
        $physical['ubicacion'] = $ubicacion;
        $this->postInventory($physical, 'issue', '1');
        $this->postInventory($physical, 'receipt', '1');
        $this->assertDatabaseHas('posiciones_unidad_individual', ['unidad_individual_id' => $c['unidad']->id, 'presente' => 1, 'ultimo_item_devolucion_id' => null, 'ultimo_item_prestamo_id' => null]);
    }

    public function test_sql_rejects_closure_without_movement_and_competing_draft_cannot_return_twice(): void
    {
        $c = $this->prestamo();
        $original = $c['prestamo']->lineas()->first();
        $a = $this->draft($c, $original->id);
        $b = $this->draft($c, $original->id);
        $item = $a->lineas()->first();
        try {
            DB::table('cierres_item_prestamo')->insert(['item_prestamo_id' => $original->id, 'item_devolucion_id' => $item->id, 'unidad_individual_id' => $original->unidad_individual_id]);
            $this->fail('Closure without physical movement accepted');
        } catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }
        app(ConfirmarDevolucion::class)->handle($c['actor'], $a, 1);
        try {
            app(ConfirmarDevolucion::class)->handle($c['actor'], $b, 1);
            $this->fail('Duplicate return accepted');
        } catch (ValidationException) {
            $this->assertSame('draft', $b->fresh()->estado);
        }
        $this->assertDatabaseCount('cierres_item_prestamo', 1);
    }

    public function test_exceptional_return_requires_specific_approval_and_never_reactivates_catalog(): void
    {
        $c = $this->prestamo();
        $original = $c['prestamo']->lineas()->first();
        DB::table('unidades_individuales')->where('id', $original->unidad_individual_id)->update(['activo' => false]);
        DB::table('productos')->where('id', $c['producto']->id)->update(['activo' => false]);
        $doc = $this->draft($c, $original->id);
        $item = $doc->lineas()->first();
        try {
            app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
            $this->fail('Unapproved exception accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cierres_item_prestamo', 0);
        }
        $auth = app(RegularizacionDevolucion::class)->request($c['actor'], $item, 1, 'Recepción de herramienta retirada del catálogo');
        $auth = app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, true, 'Identidad y destino revisados');
        app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
        $this->assertSame('used', $auth->fresh()->estado);
        $this->assertDatabaseCount('eventos_regularizacion_devolucion', 3);
        $this->assertDatabaseHas('productos', ['id' => $c['producto']->id, 'activo' => false]);
        $this->assertDatabaseHas('unidades_individuales', ['id' => $original->unidad_individual_id, 'activo' => false]);
        $this->assertDatabaseHas('cierres_item_prestamo', ['item_prestamo_id' => $original->id, 'autorizacion_regularizacion_id' => $auth->id]);
        $this->expectException(HttpException::class);
        app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, false, 'Intento de revocación posterior');
    }

    public function test_revocation_expiration_and_content_changes_invalidate_exception(): void
    {
        $c = $this->prestamo();
        $original = $c['prestamo']->lineas()->first();
        DB::table('unidades_individuales')->where('id', $original->unidad_individual_id)->update(['activo' => false]);
        $doc = $this->draft($c, $original->id);
        $item = $doc->lineas()->first();
        $auth = app(RegularizacionDevolucion::class)->request($c['actor'], $item, 1, 'Solicitar recepción');
        $auth = app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, true, 'Revisado');
        app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, false, 'Destino pendiente de revisión');
        try {
            app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
            $this->fail('Revoked authorization accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cierres_item_prestamo', 0);
        }
        $auth = app(RegularizacionDevolucion::class)->request($c['actor'], $item, 1, 'Nueva revisión');
        $auth = app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, true, 'Revisado');
        $this->travel(25)->hours();
        try {
            app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expired authorization accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('cierres_item_prestamo', 0);
        }$this->travelBack();
        DB::table('productos')->where('id', $c['producto']->id)->increment('version_bloqueo');
        $this->expectException(ValidationException::class);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
    }

    public function test_injected_failure_rolls_back_return_custody_closure_and_authorization(): void
    {
        $c = $this->prestamo();
        $original = $c['prestamo']->lineas()->first();
        DB::table('unidades_individuales')->where('id', $original->unidad_individual_id)->update(['activo' => false]);
        $doc = $this->draft($c, $original->id);
        $auth = app(RegularizacionDevolucion::class)->request($c['actor'], $doc->lineas()->first(), 1, 'Recepción excepcional');
        $auth = app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, true, 'Revisado');
        Event::listen('worker_operation.line_posted', fn () => throw new \RuntimeException('Injected return fallo'));
        try {
            app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expected rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected return fallo', $e->getMessage());
        }
        $this->assertDatabaseCount('movimientos_inventario', 3);
        $this->assertDatabaseCount('cierres_item_prestamo', 0);
        $this->assertSame('approved', $auth->fresh()->estado);
        $this->assertDatabaseHas('custodias_unidad_individual', ['unidad_individual_id' => $original->unidad_individual_id, 'item_prestamo_actual_id' => $original->id]);
    }

    public function test_inactive_existing_pair_can_be_authorized_but_inactive_destination_cannot(): void
    {
        $c = $this->prestamo();
        $original = $c['prestamo']->lineas()->first();
        DB::table('ubicaciones_producto')->where('producto_id', $c['producto']->id)->where('ubicacion_id', $c['ubicacion']->id)->update(['activo' => false]);
        $doc = $this->draft($c, $original->id);
        $auth = app(RegularizacionDevolucion::class)->request($c['actor'], $doc->lineas()->first(), 1, 'Asociación retirada');
        app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, true, 'Destino comprobado');
        app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseHas('ubicaciones_producto', ['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'activo' => false]);
        DB::table('ubicaciones')->where('id', $c['ubicacion']->id)->update(['activo' => false]);
        $this->expectException(ValidationException::class);
        $this->draft($c, $c['prestamo']->lineas()->latest('id')->first()->id);
    }

    public function test_editing_draft_revokes_approval_without_erasing_its_history(): void
    {
        $c = $this->prestamo();
        $original = $c['prestamo']->lineas()->first();
        DB::table('unidades_individuales')->where('id', $original->unidad_individual_id)->update(['activo' => false]);
        $doc = $this->draft($c, $original->id);
        $item = $doc->lineas()->first();
        $auth = app(RegularizacionDevolucion::class)->request($c['actor'], $item, 1, 'Recibir herramienta');
        app(RegularizacionDevolucion::class)->decide($c['actor'], $auth, true, 'Revisado');
        $doc = app(GuardarDevolucion::class)->handle($c['actor'], $doc, ['version_bloqueo' => 1, 'prestamo_id' => $c['prestamo']->id, 'fecha_documento' => '2026-10-19', 'observaciones' => 'Revisión posterior', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $original->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $this->assertSame($item->id, $doc->lineas()->first()->id);
        $this->assertSame('revoked', $auth->fresh()->estado);
        $this->assertDatabaseCount('eventos_regularizacion_devolucion', 3);
        $this->expectException(ValidationException::class);
        app(ConfirmarDevolucion::class)->handle($c['actor'], $doc, 2);
    }
}
