<?php

namespace Tests\Feature\Inventory;

use App\Actions\Catalogo\GuardarProducto;
use App\Actions\Catalogo\GuardarUbicacionProducto;
use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Models\DocumentoInventario;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InventoryManagementTest extends TestCase
{
    use CreatesInventory;

    public function test_http_draft_edit_confirm_retry_and_queries(): void
    {
        $c = $this->inventoryContext();
        $data = $this->inventoryData($c);
        $this->signIn($c['actor'])->get(route('documentos-inventario.create', ['tipo' => 'receipt']))->assertOk()->assertSee('Guardar borrador');
        $this->post(route('documentos-inventario.store'), $data + ['creado_por' => 999999, 'estado' => 'confirmed'])->assertSessionHasNoErrors()->assertRedirect();
        $documento = DocumentoInventario::firstOrFail();
        $this->assertSame('draft', $documento->estado);
        $this->assertSame($c['actor']->id, $documento->creado_por);
        $this->assertDatabaseCount('existencias_ubicacion', 0);
        $this->get(route('documentos-inventario.edit', $documento))->assertOk();
        $data['lineas'][0]['cantidad'] = '5';
        $data['version_bloqueo'] = $documento->version_bloqueo;
        $this->put(route('documentos-inventario.update', $documento), $data)->assertSessionHasNoErrors();
        $this->put(route('documentos-inventario.update', $documento), $data)->assertConflict();
        $version = $documento->fresh()->version_bloqueo;
        foreach ([1, 2] as $retry) {
            $this->post(route('documentos-inventario.confirm', $documento), ['version_bloqueo' => $version])->assertSessionHasNoErrors()->assertRedirect();
        }
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '5.000000']);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->get(route('documentos-inventario.show', $documento))->assertOk()->assertSee('Confirmado');
        $this->get(route('documentos-inventario.index', ['q' => 'Contraparte']))->assertOk()->assertSee($documento->codigo);
        $this->get(route('documentos-inventario.index', ['q' => $documento->codigo]))->assertOk()->assertSee($documento->codigo);
        $this->get(route('stock.index', ['producto_id' => $c['producto']->id]))->assertOk()->assertSee('5.000000');
        $this->get(route('movements.index', ['ubicacion_id' => $c['ubicacion']->id]))->assertOk()->assertSee('5.000000');
        $this->get(route('documentos-inventario.edit', $documento))->assertForbidden();
        $this->post(route('documentos-inventario.cancel', $documento), ['version_bloqueo' => $version, 'motivo' => 'No permitido'])->assertForbidden();
        $this->delete(route('documentos-inventario.show', $documento))->assertStatus(405);
    }

    public function test_issue_insufficient_stock_and_exact_decimal_arithmetic(): void
    {
        $c = $this->inventoryContext();
        $c['producto']->unidadMedida->update(['decimales' => 6]);
        $this->postInventory($c, cantidad: '0.300001');
        $this->postInventory($c, 'issue', '0.1');
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '0.200001']);
        $bad = $this->inventoryDraft($c, 'issue', '0.200002');
        try {
            app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $bad, 1);
            $this->fail('Insufficient stock accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lineas', $e->errors());
        }
        $this->assertSame('draft', $bad->fresh()->estado);
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '0.200001']);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public static function invalidQuantities(): array
    {
        return array_map(fn ($v) => [$v], ['0', '-1', '1e2', '1,5', '1000000000000', '0.1234567', [], 1.5, null]);
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_quantities_never_create_a_partial_draft(mixed $cantidad): void
    {
        $c = $this->inventoryContext();
        $data = $this->inventoryData($c);
        $data['lineas'][0]['cantidad'] = $cantidad;
        try {
            app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $data);
            $this->fail('Invalid cantidad accepted');
        } catch (ValidationException) {
            $this->assertDatabaseCount('documentos_inventario', 0);
        }
    }

    public function test_missing_pair_inactive_product_and_precision_are_revalidated_at_confirmation(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        UbicacionProducto::where('producto_id', $c['producto']->id)->update(['activo' => false]);
        $this->signIn($c['actor'])->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas.1');
        UbicacionProducto::where('producto_id', $c['producto']->id)->update(['activo' => true]);
        $c['producto']->update(['activo' => false]);
        $this->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas.1');
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_failure_after_posting_a_line_rolls_back_every_effect_including_opening_marker(): void
    {
        $c = $this->inventoryContext('individual');
        $doc = $this->inventoryDraft($c, 'opening', '1');
        Event::listen('inventory.line_posted', fn () => throw new \RuntimeException('Injected rollback'));
        try {
            app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $doc, 1);
            $this->fail('Expected injected fallo');
        } catch (\RuntimeException $e) {
            $this->assertSame('Injected rollback', $e->getMessage());
        } finally {
            Event::forget('inventory.line_posted');
        }
        foreach (['existencias_ubicacion', 'movimientos_inventario', 'posiciones_unidad_individual', 'productos_existencia_inicial'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame('draft', $doc->fresh()->estado);
        app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $doc, 1);
        $this->assertSame('confirmed', $doc->fresh()->estado);
    }

    public function test_individual_catalog_is_not_stock_and_units_can_exit_and_reenter(): void
    {
        $c = $this->inventoryContext('individual');
        $this->assertDatabaseCount('posiciones_unidad_individual', 0);
        $this->postInventory($c, cantidad: '1');
        $duplicate = $this->inventoryDraft($c, cantidad: '1');
        $this->signIn($c['actor'])->post(route('documentos-inventario.confirm', $duplicate), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas');
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->postInventory($c, 'issue', '1');
        $this->assertDatabaseHas('posiciones_unidad_individual', ['presente' => false, 'ubicacion_id' => null]);
        $this->postInventory($c, cantidad: '1');
        $this->assertDatabaseHas('posiciones_unidad_individual', ['presente' => true, 'ubicacion_id' => $c['ubicacion']->id]);
        $this->assertNull($c['unidad']->fresh()->ubicacion_id);
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '1.000000']);
        $this->get(route('stock.index'))->assertOk()->assertSee($c['unidad']->identificador);
    }

    public function test_wrong_unit_product_duplicate_selection_and_count_are_rejected(): void
    {
        $c = $this->inventoryContext('individual');
        $other = $this->inventoryContext('individual');
        foreach ([[$other['unidad']->id], [$c['unidad']->id, $c['unidad']->id], []] as $ids) {
            $data = $this->inventoryData($c, cantidad: '1');
            $data['lineas'][0]['unit_ids'] = $ids;
            $this->signIn($c['actor'])->post(route('documentos-inventario.store'), $data)->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('documentos_inventario', 0);
    }

    public function test_opening_is_once_per_product_even_after_full_issue_and_prior_movements_block_it(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'opening', '3');
        $this->postInventory($c, 'issue', '3');
        $doc = $this->inventoryDraft($c, 'opening', '1');
        $this->signIn($c['actor'])->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas');
        $this->assertDatabaseCount('productos_existencia_inicial', 1);
        $other = $this->inventoryContext();
        $this->postInventory($other);
        $doc = $this->inventoryDraft($other, 'opening');
        $this->signIn($other['actor'])->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas');
    }

    public function test_external_reference_is_optional_and_matches_warn_without_blocking(): void
    {
        $c = $this->inventoryContext();
        $data = $this->inventoryData($c);
        $data['referencia_externa'] = 'EXT-REPEAT';
        $one = app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $data);
        $two = app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $data);
        $this->signIn($c['actor'])->get(route('documentos-inventario.show', $two))->assertOk()->assertSee('Posible coincidencia documental')->assertSee($one->codigo);
        $this->post(route('documentos-inventario.confirm', $two), ['version_bloqueo' => 1])->assertSessionHasNoErrors();
    }

    public function test_cancelled_draft_has_no_stock_and_cannot_confirm(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        $this->signIn($c['actor'])->post(route('documentos-inventario.cancel', $doc), ['version_bloqueo' => 1])->assertSessionHasErrors('motivo');
        $this->post(route('documentos-inventario.cancel', $doc), ['version_bloqueo' => 1, 'motivo' => 'Error de digitación'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $doc->fresh()->estado);
        $this->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 2])->assertForbidden();
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_direct_actions_and_http_respect_permissions_and_revocation(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        $auditor = Usuario::factory()->create(['rol_id' => 3]);
        $this->signIn($auditor)->get(route('stock.index'))->assertOk();
        $this->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertForbidden();
        $this->post(route('documentos-inventario.store'), $this->inventoryData($c))->assertForbidden();
        $manager = Usuario::factory()->create(['rol_id' => 2]);
        $opening = $this->inventoryDraft($c, 'opening');
        $this->signIn($manager)->post(route('documentos-inventario.confirm', $opening), ['version_bloqueo' => 1])->assertForbidden();
        $actor = $c['actor'];
        $actor->load('rol');
        DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', DB::table('permisos')->where('codigo', 'remittances.confirm_receipt')->value('id'))->update(['activo' => false]);
        DB::table('roles')->where('id', 1)->increment('version_permisos');
        try {
            app(ConfirmarDocumentoInventario::class)->handle($actor, $doc, 1);
            $this->fail('Revoked operator accepted');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('movimientos_inventario', 0);
        }
        $this->signIn($actor)->get(route('stock.index'))->assertRedirect(route('login'));
    }

    public function test_stock_protects_catalog_product_pair_and_individual_identity(): void
    {
        $c = $this->inventoryContext('individual');
        $this->postInventory($c, cantidad: '1');
        try {
            app(GuardarUnidadIndividual::class)->handle($c['actor'], $c['unidad'], ['activo' => false, 'version_bloqueo' => 1], true);
            $this->fail('Present unidad deactivated');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('activo', $e->errors());
        }
        $q = $this->inventoryContext();
        $this->postInventory($q);
        try {
            app(GuardarProducto::class)->handle($q['actor'], $q['producto'], ['activo' => false, 'version_bloqueo' => 1], true);
            $this->fail('Producto with stock deactivated');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('activo', $e->errors());
        }
        try {
            app(GuardarUbicacionProducto::class)->handle($q['actor'], $q['producto'], UbicacionProducto::where('producto_id', $q['producto']->id)->firstOrFail(), ['activo' => false, 'version_bloqueo' => 1]);
            $this->fail('Pair with stock deactivated');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('activo', $e->errors());
        }
    }

    public function test_required_header_fields_duplicate_pairs_and_truncated_forms_are_rejected(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        foreach (['fecha_documento', 'identificacion_contraparte', 'nombre_contraparte'] as $field) {
            $data = $this->inventoryData($c);
            unset($data[$field]);
            $this->post(route('documentos-inventario.store'), $data)->assertSessionHasErrors($field);
        }
        $data = $this->inventoryData($c, 'opening');
        unset($data['fecha_corte']);
        $this->post(route('documentos-inventario.store'), $data)->assertSessionHasErrors('fecha_corte');
        $data = $this->inventoryData($c);
        $data['expected_line_count'] = 2;
        $this->post(route('documentos-inventario.store'), $data)->assertSessionHasErrors('lineas');
        $data['lineas'][] = $data['lineas'][0];
        $this->post(route('documentos-inventario.store'), $data)->assertSessionHasErrors('lineas');
        $this->assertDatabaseCount('documentos_inventario', 0);
    }

    public function test_decimal_capacity_overflow_does_not_add_a_movement(): void
    {
        $c = $this->inventoryContext();
        $c['producto']->unidadMedida->update(['decimales' => 6]);
        $this->postInventory($c, cantidad: '999999999999.999999');
        $doc = $this->inventoryDraft($c, cantidad: '0.000001');
        $this->signIn($c['actor'])->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas');
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '999999999999.999999']);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_opening_spans_multiple_locations_with_one_product_marker(): void
    {
        $c = $this->inventoryContext();
        $ubicacion = Ubicacion::factory()->create();
        UbicacionProducto::factory()->create(['producto_id' => $c['producto']->id, 'ubicacion_id' => $ubicacion->id]);
        $data = $this->inventoryData($c, 'opening');
        $data['lineas'][] = array_replace($data['lineas'][0], ['ubicacion_id' => $ubicacion->id, 'cantidad' => '2']);
        $doc = app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $data);
        app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $doc, 1);
        $this->assertDatabaseCount('productos_existencia_inicial', 1);
        $this->assertDatabaseCount('existencias_ubicacion', 2);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertSame('5.000000', DB::table('existencias_ubicacion')->sum('cantidad'));
    }
}
