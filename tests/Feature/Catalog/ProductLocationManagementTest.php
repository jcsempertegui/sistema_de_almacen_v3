<?php

namespace Tests\Feature\Catalog;

use App\Models\Location;
use App\Models\ProductLocation;
use App\Models\User;
use App\Services\ProductReferences;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductLocationManagementTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_multiple_locations_are_registered_toggled_and_searched_without_quantities(): void
    {
        $actor = User::factory()->create(['role_id' => 2]);
        $locations = Location::factory()->count(2)->create();
        $product = $this->savedProduct($actor, ['location_ids' => $locations->pluck('id')->all()]);
        $this->assertSame(2, $product->locations()->count());
        $this->assertSame(3, $product->lock_version);
        $this->signIn($actor)->get(route('product-locations.index', $product))->assertOk()->assertSee('no representan cantidades');
        $this->get(route('product-locations.create', $product))->assertOk();
        $link = $product->productLocations()->firstOrFail();
        foreach ([0, 1] as $state) {
            $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => $state, 'lock_version' => $product->fresh()->lock_version])->assertSessionHasNoErrors();
            $this->assertSame((bool) $state, $link->fresh()->is_active);
        }
        $this->get(route('product-locations.index', [$product, 'q' => $link->location->code, 'status' => 'active']))->assertOk()->assertSee($link->location->code);
        $this->assertFalse(Schema::hasColumn('product_locations', 'quantity'));
    }

    public function test_inactive_non_storable_and_duplicate_associations_are_rejected(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $product = $this->savedProduct($actor);
        $this->signIn($actor);
        foreach ([['is_active' => false], ['is_storable' => false]] as $changes) {
            $location = Location::factory()->create($changes);
            $this->post(route('product-locations.store', $product), ['location_id' => $location->id, 'is_active' => 1, 'lock_version' => 1])->assertSessionHasErrors('location_id');
        }
        $location = Location::factory()->create();
        $this->post(route('product-locations.store', $product), ['location_id' => $location->id, 'is_active' => 1, 'lock_version' => 1])->assertSessionHasNoErrors();
        $link = ProductLocation::firstOrFail();
        $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => 0, 'lock_version' => 2])->assertSessionHasNoErrors();
        $this->post(route('product-locations.store', $product), ['location_id' => $location->id, 'is_active' => 1, 'lock_version' => 3])->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('product_locations', 1);
        $this->assertSame(3, $product->fresh()->lock_version);
    }

    public function test_failure_of_an_initial_association_rolls_back_the_new_product_and_prior_links(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $valid = Location::factory()->create();
        $invalid = Location::factory()->create(['is_storable' => false]);
        $this->signIn($actor)->post(route('products.store'), $this->productData(['location_ids' => [$valid->id, $invalid->id]]))->assertSessionHasErrors('location_id');
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_locations', 0);
    }

    public function test_location_deactivation_is_blocked_until_links_are_inactive_and_history_remains(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $location = Location::factory()->create();
        $product = $this->savedProduct($actor, ['location_ids' => [$location->id]]);
        $link = $product->productLocations()->firstOrFail();
        $this->signIn($actor)->patch(route('locations.status', $location), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => 0, 'lock_version' => 2])->assertSessionHasNoErrors();
        $this->patch(route('locations.status', $location), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => 1, 'lock_version' => 3])->assertSessionHasErrors('location_id');
        $this->assertDatabaseHas('product_locations', ['id' => $link->id, 'location_id' => $location->id, 'is_active' => 0]);
    }

    public function test_nested_ownership_stale_versions_and_assigned_unit_guard_are_enforced(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $location = Location::factory()->create();
        $product = $this->savedProduct($actor, ['location_ids' => [$location->id]]);
        $other = $this->savedProduct($actor);
        $link = $product->productLocations()->firstOrFail();
        $this->signIn($actor)->patch(route('product-locations.status', [$other, $link]), ['is_active' => 0, 'lock_version' => 1])->assertNotFound();
        $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => 0, 'lock_version' => 1])->assertStatus(409);
        $this->mock(ProductReferences::class, function ($mock) use ($product, $location) {
            $mock->shouldReceive('hasAssignedUnits')->with($product->id, $location->id)->once()->andReturnTrue();
        });
        $this->patch(route('product-locations.status', [$product, $link]), ['is_active' => 0, 'lock_version' => 2])->assertSessionHasErrors('is_active');
        $this->assertTrue($link->fresh()->is_active);
    }

    public function test_inactive_product_cannot_receive_active_location_links_and_link_history_freezes_structure(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $location = Location::factory()->create();
        $product = $this->savedProduct($actor, ['is_active' => 0]);
        $this->signIn($actor)->post(route('product-locations.store', $product), ['location_id' => $location->id, 'is_active' => 1, 'lock_version' => 1])->assertSessionHasErrors('location_id');
        $link = new ProductLocation(['is_active' => false]);
        $link->product_id = $product->id;
        $link->location_id = $location->id;
        $link->save();
        $this->put(route('products.update', $product), $this->productPayload($product, ['control_mode' => 'individual']))->assertSessionHasErrors('category_id');
        $this->assertDatabaseHas('product_locations', ['id' => $link->id, 'is_active' => 0]);
    }
}
