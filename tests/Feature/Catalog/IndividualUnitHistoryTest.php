<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class IndividualUnitHistoryTest extends TestCase
{
    use CreatesIndividualUnitCatalog, CreatesProductCatalog;

    public function test_real_units_protect_product_structure_and_active_units_prevent_both_deactivation_endpoints(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $unidad = $this->savedIndividual($actor);
        $producto = $unidad->producto;
        $this->signIn($actor)->patch(route('productos.estado', $producto), ['activo' => 0, 'version_bloqueo' => $producto->version_bloqueo])->assertSessionHasErrors('activo');
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['activo' => 0]))->assertSessionHasErrors('activo');
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['modo_control' => 'quantity']))->assertSessionHasErrors();
        $before = $unidad->fresh()->getAttributes();
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 0, 'version_bloqueo' => 1])->assertSessionHasNoErrors();
        foreach (['id', 'identificador', 'producto_id', 'ubicacion_id', 'numero_serie', 'creado_por', 'fecha_creacion'] as $key) {
            $this->assertSame($before[$key] ?? null, $unidad->fresh()->getAttributes()[$key] ?? null);
        }
        $this->patch(route('productos.estado', $producto), ['activo' => 0, 'version_bloqueo' => $producto->version_bloqueo])->assertSessionHasNoErrors();
        $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad->fresh(), ['observaciones' => 'Referencia conservada']))->assertSessionHasNoErrors();
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 1, 'version_bloqueo' => $unidad->fresh()->version_bloqueo])->assertSessionHasErrors('producto_id');
        $this->assertDatabaseCount('unidades_individuales', 1);
    }

    public function test_real_assigned_units_even_inactive_prevent_link_and_location_history_changes(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $ubicacion = Ubicacion::factory()->create();
        $data = $this->individualData(['ubicacion_id' => $ubicacion->id]);
        $link = UbicacionProducto::factory()->create(['producto_id' => $data['producto_id'], 'ubicacion_id' => $ubicacion->id]);
        $unidad = app(GuardarUnidadIndividual::class)->handle($actor, null, $data);
        $this->signIn($actor);
        foreach ([1, 0] as $active) {
            $unidad->update(['activo' => $active]);
            $this->patch(route('ubicaciones-producto.estado', [$unidad->producto, $link]), ['activo' => 0, 'version_bloqueo' => $unidad->producto->version_bloqueo])->assertSessionHasErrors('activo');
            $this->patch(route('ubicaciones.estado', $ubicacion), ['activo' => 0])->assertSessionHasErrors('activo');
            $detalle = array_intersect_key($ubicacion->getAttributes(), array_flip($ubicacion->getFillable()));
            $this->put(route('ubicaciones.update', $ubicacion), array_replace($detalle, ['tipo' => 'cabinet']))->assertSessionHasErrors();
            $this->put(route('ubicaciones.update', $ubicacion), array_replace($detalle, ['permite_almacenar' => 0]))->assertSessionHasErrors();
        }
        // Inconsistent legacy estado is simulated only in testing: the unidad guard must still protect the ubicacion.
        $link->update(['activo' => false]);
        $this->patch(route('ubicaciones.estado', $ubicacion), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 1, 'version_bloqueo' => $unidad->version_bloqueo])->assertSessionHasErrors('ubicacion_id');
        $this->assertSame($ubicacion->id, $unidad->fresh()->ubicacion_id);
        $this->assertTrue($link->fresh()->exists);
    }

    public function test_failed_association_validation_rolls_back_metadata_and_version(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $ubicacion = Ubicacion::factory()->create();
        $data = $this->individualData(['ubicacion_id' => $ubicacion->id]);
        $link = UbicacionProducto::factory()->create(['producto_id' => $data['producto_id'], 'ubicacion_id' => $ubicacion->id]);
        $unidad = app(GuardarUnidadIndividual::class)->handle($actor, null, $data);
        $link->update(['activo' => false]);
        $before = $unidad->fresh()->getAttributes();
        try {
            app(GuardarUnidadIndividual::class)->handle($actor, $unidad, $this->individualPayload($unidad, ['observaciones' => 'No guardar', 'numero_serie' => 'Nueva']));
            $this->fail('Debe rechazar una asociación inactiva.');
        } catch (ValidationException) {
            $this->assertSame($before, $unidad->fresh()->getAttributes());
        }
        $link->update(['activo' => true]);
        // Force a fallo AFTER INSERT to prove the complete accion rolls back, including authors and identity.
        $listener = function () {
            throw new \RuntimeException('forced rollback');
        };
        UnidadIndividual::created($listener);
        try {
            app(GuardarUnidadIndividual::class)->handle($actor, null, $this->individualData());
            $this->fail('Debe propagar el fallo.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced rollback', $e->getMessage());
            $this->assertSame(1, DB::table('unidades_individuales')->count());
        } finally {
            UnidadIndividual::flushEventListeners();
        }
    }
}
