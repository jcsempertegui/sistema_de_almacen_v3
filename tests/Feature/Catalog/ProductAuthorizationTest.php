<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarProducto;
use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_auditor_reads_but_direct_writes_are_forbidden(): void
    {
        $producto = Producto::factory()->create();
        $ubicacion = Ubicacion::factory()->create();
        $link = UbicacionProducto::factory()->create(['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        foreach ([route('productos.index'), route('productos.show', $producto), route('ubicaciones-producto.index', $producto)] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([route('productos.create'), route('productos.edit', $producto), route('ubicaciones-producto.create', $producto)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('productos.store'), $this->productData())->assertForbidden();
        $this->post(route('productos.preview'), $this->productData())->assertForbidden();
        $this->put(route('productos.update', $producto), $this->productPayload($producto))->assertForbidden();
        $this->patch(route('productos.estado', $producto), ['activo' => 0, 'version_bloqueo' => 1])->assertForbidden();
        $this->post(route('ubicaciones-producto.store', $producto), ['ubicacion_id' => $ubicacion->id, 'activo' => 1, 'version_bloqueo' => 1])->assertForbidden();
        $this->patch(route('ubicaciones-producto.estado', [$producto, $link]), ['activo' => 0, 'version_bloqueo' => 1])->assertForbidden();
        $this->assertTrue($producto->fresh()->activo);
        $this->assertTrue($link->fresh()->activo);
    }

    public function test_custom_role_names_do_not_grant_capabilities_and_status_requires_its_own_permission(): void
    {
        $rol = Rol::create(['codigo' => 'custom_product', 'nombre' => 'Administrador']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->signIn($actor)->get(route('productos.index'))->assertForbidden();
        $ids = Permiso::whereIn('codigo', ['products.view', 'products.update'])->pluck('id');
        $rol->permisos()->syncWithPivotValues($ids, ['activo' => true]);
        $producto = Producto::factory()->create();
        $this->signIn($actor->fresh())->put(route('productos.update', $producto), $this->productPayload($producto, ['activo' => 0]))->assertForbidden();
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['nombre' => 'Permitido']))->assertSessionHasNoErrors();
        $this->assertTrue($producto->fresh()->activo);
    }

    public function test_initial_links_cannot_bypass_location_permission_and_atomic_save_is_preserved(): void
    {
        $rol = Rol::create(['codigo' => 'only_product_create', 'nombre' => 'Registro']);
        $rol->permisos()->syncWithPivotValues(Permiso::where('codigo', 'products.create')->pluck('id'), ['activo' => true]);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->signIn($actor)->post(route('productos.store'), $this->productData(['location_ids' => [Ubicacion::factory()->create()->id]]))->assertForbidden();
        $this->assertDatabaseCount('productos', 0);
        $this->assertDatabaseCount('ubicaciones_producto', 0);
    }

    public function test_permission_changes_invalidate_sessions_and_stale_action_operators(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $this->signIn($actor);
        $actor->load('rol');
        $codes = array_diff($actor->rol->codigosPermisosAsignados(), ['products.create']);
        app(GuardarRol::class)->handle($admin, $actor->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->getJson(route('productos.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->post(route('productos.store'), $this->productData())->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(GuardarProducto::class)->handle($actor, null, $this->productData());
    }

    public function test_guests_and_csrf_are_checked_on_product_and_location_mutations(): void
    {
        $producto = Producto::factory()->create();
        $ubicacion = Ubicacion::factory()->create();
        $this->getJson(route('productos.index'))->assertUnauthorized();
        $this->postJson(route('productos.store'), [])->assertUnauthorized();
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->app->instance('env', 'local');
        $this->post(route('productos.store'), $this->productData())->assertStatus(419);
        $this->post(route('productos.preview'), $this->productData())->assertStatus(419);
        $this->put(route('productos.update', $producto), $this->productPayload($producto))->assertStatus(419);
        $this->patch(route('productos.estado', $producto), ['activo' => 0, 'version_bloqueo' => 1])->assertStatus(419);
        $this->post(route('ubicaciones-producto.store', $producto), ['ubicacion_id' => $ubicacion->id, 'activo' => 1, 'version_bloqueo' => 1])->assertStatus(419);
    }
}
