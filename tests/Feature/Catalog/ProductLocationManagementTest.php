<?php

namespace Tests\Feature\Catalog;

use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductLocationManagementTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_multiple_locations_are_registered_toggled_and_searched_without_quantities(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $ubicaciones = Ubicacion::factory()->count(2)->create();
        $producto = $this->savedProduct($actor, ['location_ids' => $ubicaciones->pluck('id')->all()]);
        $this->assertSame(2, $producto->ubicaciones()->count());
        $this->assertSame(3, $producto->version_bloqueo);
        $this->signIn($actor)->get(route('ubicaciones-producto.index', $producto))->assertOk()->assertSee('no representan cantidades');
        $this->get(route('ubicaciones-producto.create', $producto))->assertOk();
        $link = $producto->ubicacionesProducto()->firstOrFail();
        foreach ([0, 1] as $state) {
            $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => $state, 'version_bloqueo' => $producto->fresh()->version_bloqueo])->assertSessionHasNoErrors();
            $this->assertSame((bool) $state, $link->fresh()->activo);
        }
        $this->get(route('ubicaciones-producto.index', [$producto, 'q' => $link->ubicacion->codigo, 'estado' => 'active']))->assertOk()->assertSee($link->ubicacion->codigo);
        $this->assertFalse(Schema::hasColumn('ubicaciones_producto', 'cantidad'));
    }

    public function test_inactive_non_storable_and_duplicate_associations_are_rejected(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $producto = $this->savedProduct($actor);
        $this->signIn($actor);
        foreach ([['activo' => false], ['permite_almacenar' => false]] as $changes) {
            $ubicacion = Ubicacion::factory()->create($changes);
            $this->post(route('ubicaciones-producto.store', $producto), ['ubicacion_id' => $ubicacion->id, 'activo' => 1, 'version_bloqueo' => 1])->assertSessionHasErrors('ubicacion_id');
        }
        $ubicacion = Ubicacion::factory()->create();
        $this->post(route('ubicaciones-producto.store', $producto), ['ubicacion_id' => $ubicacion->id, 'activo' => 1, 'version_bloqueo' => 1])->assertSessionHasNoErrors();
        $link = UbicacionProducto::firstOrFail();
        $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => 0, 'version_bloqueo' => 2])->assertSessionHasNoErrors();
        $this->post(route('ubicaciones-producto.store', $producto), ['ubicacion_id' => $ubicacion->id, 'activo' => 1, 'version_bloqueo' => 3])->assertSessionHasErrors('ubicacion_id');
        $this->assertDatabaseCount('ubicaciones_producto', 1);
        $this->assertSame(3, $producto->fresh()->version_bloqueo);
    }

    public function test_failure_of_an_initial_association_rolls_back_the_new_product_and_prior_links(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $valid = Ubicacion::factory()->create();
        $invalid = Ubicacion::factory()->create(['permite_almacenar' => false]);
        $this->signIn($actor)->post(route('productos.store'), $this->productData(['location_ids' => [$valid->id, $invalid->id]]))->assertSessionHasErrors('ubicacion_id');
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('ubicaciones_producto', 0);
    }

    public function test_location_deactivation_is_blocked_until_links_are_inactive_and_history_remains(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $ubicacion = Ubicacion::factory()->create();
        $producto = $this->savedProduct($actor, ['location_ids' => [$ubicacion->id]]);
        $link = $producto->ubicacionesProducto()->firstOrFail();
        $this->signIn($actor)->patch(route('ubicaciones.estado', $ubicacion), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => 0, 'version_bloqueo' => 2])->assertSessionHasNoErrors();
        $this->patch(route('ubicaciones.estado', $ubicacion), ['activo' => 0])->assertSessionHasNoErrors();
        $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => 1, 'version_bloqueo' => 3])->assertSessionHasErrors('ubicacion_id');
        $this->assertDatabaseHas('ubicaciones_producto', ['id' => $link->id, 'ubicacion_id' => $ubicacion->id, 'activo' => 0]);
    }

    public function test_nested_ownership_stale_versions_and_assigned_unit_guard_are_enforced(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $ubicacion = Ubicacion::factory()->create();
        $producto = $this->savedProduct($actor, ['location_ids' => [$ubicacion->id], 'modo_control' => 'individual']);
        $other = $this->savedProduct($actor);
        $link = $producto->ubicacionesProducto()->firstOrFail();
        $this->signIn($actor)->patch(route('ubicaciones-producto.estado', [$other, $link]), ['activo' => 0, 'version_bloqueo' => 1])->assertNotFound();
        $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => 0, 'version_bloqueo' => 1])->assertStatus(409);
        UnidadIndividual::factory()->create(['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id]);
        $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => 0, 'version_bloqueo' => 2])->assertSessionHasErrors('activo');
        $this->assertTrue($link->fresh()->activo);
    }

    public function test_inactive_product_cannot_receive_active_location_links_and_link_history_freezes_structure(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $ubicacion = Ubicacion::factory()->create();
        $producto = $this->savedProduct($actor, ['activo' => 0]);
        $this->signIn($actor)->post(route('ubicaciones-producto.store', $producto), ['ubicacion_id' => $ubicacion->id, 'activo' => 1, 'version_bloqueo' => 1])->assertSessionHasErrors('ubicacion_id');
        $link = new UbicacionProducto(['activo' => false]);
        $link->producto_id = $producto->id;
        $link->ubicacion_id = $ubicacion->id;
        $link->save();
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['modo_control' => 'individual']))->assertSessionHasErrors('categoria_id');
        $this->assertDatabaseHas('ubicaciones_producto', ['id' => $link->id, 'activo' => 0]);
    }
}
