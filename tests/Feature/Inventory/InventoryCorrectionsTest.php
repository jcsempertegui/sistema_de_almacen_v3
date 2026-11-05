<?php

namespace Tests\Feature\Inventory;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Actions\Roles\GuardarRol;
use App\Models\CorreccionInventario;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InventoryCorrectionsTest extends TestCase
{
    use CreatesInventory;

    private function adjustment(array $c, string $count = '5'): CorreccionInventario
    {
        return app(CorreccionesInventario::class)->save($c['actor'], null, ['tipo' => 'adjustment', 'motivo' => 'Conteo físico revisado', 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad_contada' => $count, 'unit_ids' => []]]]);
    }

    private function authorize(array $c, CorreccionInventario $doc): CorreccionInventario
    {
        $svc = app(CorreccionesInventario::class);
        $doc = $svc->transition($c['actor'], $doc, 'request', $doc->version_bloqueo);
        $admin = Usuario::factory()->create(['rol_id' => 1]);

        return $svc->transition($admin, $doc, 'approve', $doc->version_bloqueo, 'Conteo y documento verificados');
    }

    private function compensate(array $c, string $clase, int $id): CorreccionInventario
    {
        return app(CorreccionesInventario::class)->save($c['actor'], null, ['tipo' => 'compensation', 'motivo' => 'Corrección física del documento', 'fecha_documento' => '2026-10-20', 'tipo_origen' => $clase, 'origen_id' => $id]);
    }

    public function test_adjustment_requires_different_authorizer_and_posts_once(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->adjustment($c);
        $svc = app(CorreccionesInventario::class);
        $svc->transition($c['actor'], $doc, 'request', 1);
        try {
            $svc->transition($c['actor'], $doc, 'approve', 1, 'Revisado');
            $this->fail('Self authorization');
        } catch (ValidationException) {
            $this->assertDatabaseCount('movimientos_inventario', 1);
        }
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $doc = $svc->transition($admin, $doc, 'approve', 1, 'Revisado por otra persona');
        $svc->execute($c['actor'], $doc, 1);
        $svc->execute($c['actor'], $doc, 1);
        $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => '5.000000']);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseHas('autorizaciones_correccion', ['estado' => 'used']);
    }

    public function test_changed_count_revocation_expiration_and_rollback_are_effective(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $svc = app(CorreccionesInventario::class);
        $doc = $this->authorize($c, $this->adjustment($c));
        $this->postInventory($c, 'receipt', '1');
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('Stale count');
        } catch (ValidationException) {
            $this->assertSame('authorized', $doc->fresh()->estado);
        }
        $doc = $this->authorize($c, $this->adjustment($c, '6'));
        $this->travel(25)->hours();
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('Expired');
        } catch (ValidationException) {
            $this->assertDatabaseCount('movimientos_inventario', 2);
        }$this->travelBack();
        $doc = $this->authorize($c, $this->adjustment($c, '7'));
        Event::listen('inventory_correction.line_posted', fn () => throw new \RuntimeException('Injected correccion fallo'));
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('No rollback');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected correccion fallo', $e->getMessage());
        }$this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertSame('authorized', $doc->fresh()->estado);
    }

    public function test_compensation_preserves_original_and_rejects_duplicate_or_negative_balance(): void
    {
        $c = $this->inventoryContext();
        $receipt = $this->postInventory($c, 'receipt', '3');
        $svc = app(CorreccionesInventario::class);
        $doc = $this->authorize($c, $this->compensate($c, 'inventory-documents', $receipt->id));
        $original = DB::table('movimientos_inventario')->first();
        $svc->execute($c['actor'], $doc, 1);
        $svc->execute($c['actor'], $doc, 1);
        $this->assertEquals($original, DB::table('movimientos_inventario')->where('id', $original->id)->first());
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => '0.000000']);
        $this->expectException(ValidationException::class);
        $this->compensate($c, 'inventory-documents', $receipt->id);
    }

    public function test_return_compensation_reopens_cycle_and_second_return_preserves_first_closure(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $trabajador = Trabajador::factory()->create();
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        $item = $prestamo->lineas()->first();
        $makeReturn = fn () => app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $item->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $ret = $makeReturn();
        app(ConfirmarDevolucion::class)->handle($c['actor'], $ret, 1);
        $closure = DB::table('cierres_item_prestamo')->first();
        $doc = $this->authorize($c, $this->compensate($c, 'returns', $ret->id));
        app(CorreccionesInventario::class)->execute($c['actor'], $doc, 1);
        $this->assertSame('pending', $prestamo->fresh()->estado_responsabilidad);
        $this->assertDatabaseCount('ciclos_custodia_prestamo', 2);
        $trabajador->update(['activo' => false]);
        $again = $makeReturn();
        app(ConfirmarDevolucion::class)->handle($c['actor'], $again, 1);
        $this->assertSame('returned', $prestamo->fresh()->estado_responsabilidad);
        $this->assertDatabaseCount('cierres_ciclo_prestamo', 2);
        $this->assertEquals($closure, DB::table('cierres_item_prestamo')->first());
        $this->assertDatabaseCount('movimientos_inventario', 5);
        $this->assertNull($c['unidad']->fresh()->ubicacion_id);
    }

    public function test_insufficient_stock_rolls_back_an_authorized_compensation(): void
    {
        $c = $this->inventoryContext();
        $receipt = $this->postInventory($c, 'receipt', '3');
        $this->postInventory($c, 'issue', '2');
        $doc = $this->authorize($c, $this->compensate($c, 'inventory-documents', $receipt->id));
        try {
            app(CorreccionesInventario::class)->execute($c['actor'], $doc, 1);
            $this->fail('Negative stock');
        } catch (ValidationException) {
            $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => '1.000000']);
            $this->assertDatabaseCount('movimientos_inventario', 2);
            $this->assertDatabaseCount('compensaciones_documento', 0);
            $this->assertSame('authorized', $doc->fresh()->estado);
        }
    }

    public function test_loan_compensation_receives_tool_and_closes_custody_without_fabricating_return(): void
    {
        $c = $this->inventoryContext('individual');
        $c['producto']->update(['prestable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $trabajador = Trabajador::factory()->create();
        $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
        $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
        $doc = $this->authorize($c, $this->compensate($c, 'loans', $prestamo->id));
        app(CorreccionesInventario::class)->execute($c['actor'], $doc, 1);
        $this->assertSame('compensated', $prestamo->fresh()->estado_responsabilidad);
        $this->assertDatabaseCount('devoluciones_herramientas', 0);
        $this->assertDatabaseCount('cierres_ciclo_prestamo', 1);
        $this->assertDatabaseHas('custodias_unidad_individual', ['unidad_individual_id' => $c['unidad']->id, 'ciclo_actual_id' => null, 'item_prestamo_actual_id' => null]);
        $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => '1.000000']);
        $this->assertNull($c['unidad']->fresh()->ubicacion_id);
    }

    public function test_permission_loss_of_authorizer_blocks_execution_even_after_operator_relogin(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->authorize($c, $this->adjustment($c));
        $rol = Rol::findOrFail(1);
        $codes = array_diff($rol->codigosPermisosAsignados(), ['corrections.authorize']);
        app(GuardarRol::class)->handle($c['actor'], $rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->expectException(ValidationException::class);
        app(CorreccionesInventario::class)->execute($c['actor']->fresh(), $doc, 1);
    }

    public function test_regranting_authorization_permission_does_not_revive_an_old_approval(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->adjustment($c);
        $svc = app(CorreccionesInventario::class);
        $svc->transition($c['actor'], $doc, 'request', 1);
        $rol = Rol::create(['codigo' => 's5_approver', 'nombre' => 'Autorizador de prueba']);
        $permiso = Permiso::where('codigo', 'corrections.authorize')->firstOrFail();
        DB::table('permiso_rol')->insert(['rol_id' => $rol->id, 'permiso_id' => $permiso->id, 'activo' => true]);
        $approver = Usuario::factory()->create(['rol_id' => $rol->id]);
        $doc = $svc->transition($approver, $doc, 'approve', 1, 'Autorización inicial');
        app(GuardarRol::class)->handle($c['actor'], $rol, ['permisos' => []]);
        app(GuardarRol::class)->handle($c['actor'], $rol->fresh(), ['permisos' => [$permiso->id]]);
        $this->assertTrue($approver->fresh()->tienePermiso('corrections.authorize'));
        try {
            $svc->execute($c['actor'], $doc, 1);
            $this->fail('Old approval revived after regrant');
        } catch (ValidationException) {
            $this->assertSame('authorized', $doc->fresh()->estado);
            $this->assertDatabaseCount('movimientos_inventario', 1);
        }
    }

    public function test_editing_invalidates_approval_and_cancellation_never_changes_stock(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $doc = $this->authorize($c, $this->adjustment($c));
        $svc = app(CorreccionesInventario::class);
        $doc = $svc->save($c['actor'], $doc, ['tipo' => 'adjustment', 'version_bloqueo' => 1, 'fecha_documento' => '2026-10-20', 'motivo' => 'Nuevo conteo', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad_contada' => '6', 'unit_ids' => []]]]);
        $this->assertSame(2, $doc->version_bloqueo);
        $this->assertDatabaseHas('autorizaciones_correccion', ['estado' => 'revoked']);
        $svc->transition($c['actor'], $doc, 'cancel', 2);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertDatabaseHas('existencias_ubicacion', ['producto_id' => $c['producto']->id, 'cantidad' => '3.000000']);
    }
}
