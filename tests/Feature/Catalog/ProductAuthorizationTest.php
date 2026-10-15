<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Actions\Roles\SaveRole;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductAuthorizationTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_auditor_reads_but_direct_writes_are_forbidden(): void
    {
        $product = Product::factory()->create();
        $location = Location::factory()->create();
        $link = ProductLocation::factory()->create(['product_id' => $product->id, 'location_id' => $location->id]);
        $this->signIn(User::factory()->create(['role_id' => 3]));
        foreach ([route('products.index'), route('products.show', $product), route('product-locations.index', $product)] as $url) {
            $this->get($url)->assertOk();
        }
        foreach ([route('products.create'), route('products.edit', $product), route('product-locations.create', $product)] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('products.store'), $this->productData())->assertForbidden();
        $this->post(route('products.preview'), $this->productData())->assertForbidden();
        $this->put(route('products.update', $product), $this->productPayload($product))->assertForbidden();
        $this->patch(route('products.status', $product), ['is_active' => 0, 'lock_version' => 1])->assertForbidden();
        $this->post(route('product-locations.store', $product), ['location_id' => $location->id, 'is_active' => 1, 'lock_version' => 1])->assertForbidden();
        $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => 0, 'lock_version' => 1])->assertForbidden();
        $this->assertTrue($product->fresh()->is_active);
        $this->assertTrue($link->fresh()->is_active);
    }

    public function test_custom_role_names_do_not_grant_capabilities_and_status_requires_its_own_permission(): void
    {
        $role = Role::create(['code' => 'custom_product', 'name' => 'Administrador']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $this->signIn($actor)->get(route('products.index'))->assertForbidden();
        $ids = Permission::whereIn('code', ['products.view', 'products.update'])->pluck('id');
        $role->permissions()->syncWithPivotValues($ids, ['is_active' => true]);
        $product = Product::factory()->create();
        $this->signIn($actor->fresh())->put(route('products.update', $product), $this->productPayload($product, ['is_active' => 0]))->assertForbidden();
        $this->put(route('products.update', $product), $this->productPayload($product, ['name' => 'Permitido']))->assertSessionHasNoErrors();
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_initial_links_cannot_bypass_location_permission_and_atomic_save_is_preserved(): void
    {
        $role = Role::create(['code' => 'only_product_create', 'name' => 'Registro']);
        $role->permissions()->syncWithPivotValues(Permission::where('code', 'products.create')->pluck('id'), ['is_active' => true]);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $this->signIn($actor)->post(route('products.store'), $this->productData(['location_ids' => [Location::factory()->create()->id]]))->assertForbidden();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_locations', 0);
    }

    public function test_permission_changes_invalidate_sessions_and_stale_action_operators(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $actor = User::factory()->create(['role_id' => 2]);
        $this->signIn($actor);
        $actor->load('role');
        $codes = array_diff($actor->role->assignedPermissionCodes(), ['products.create']);
        app(SaveRole::class)->handle($admin, $actor->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->getJson(route('products.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->post(route('products.store'), $this->productData())->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(SaveProduct::class)->handle($actor, null, $this->productData());
    }

    public function test_guests_and_csrf_are_checked_on_product_and_location_mutations(): void
    {
        $product = Product::factory()->create();
        $location = Location::factory()->create();
        $this->getJson(route('products.index'))->assertUnauthorized();
        $this->postJson(route('products.store'), [])->assertUnauthorized();
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->app->instance('env', 'local');
        $this->post(route('products.store'), $this->productData())->assertStatus(419);
        $this->post(route('products.preview'), $this->productData())->assertStatus(419);
        $this->put(route('products.update', $product), $this->productPayload($product))->assertStatus(419);
        $this->patch(route('products.status', $product), ['is_active' => 0, 'lock_version' => 1])->assertStatus(419);
        $this->post(route('product-locations.store', $product), ['location_id' => $location->id, 'is_active' => 1, 'lock_version' => 1])->assertStatus(419);
    }
}
