<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveIndividualUnit;
use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\ProductLocation;
use App\Models\User;
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
        $actor = User::factory()->create(['role_id' => 1]);
        $unit = $this->savedIndividual($actor);
        $product = $unit->product;
        $this->signIn($actor)->patch(route('products.status', $product), ['is_active' => 0, 'lock_version' => $product->lock_version])->assertSessionHasErrors('is_active');
        $this->put(route('products.update', $product), $this->productPayload($product, ['is_active' => 0]))->assertSessionHasErrors('is_active');
        $this->put(route('products.update', $product), $this->productPayload($product, ['control_mode' => 'quantity']))->assertSessionHasErrors();
        $before = $unit->fresh()->getAttributes();
        $this->patch(route('individual-units.status', $unit), ['is_active' => 0, 'lock_version' => 1])->assertSessionHasNoErrors();
        foreach (['id', 'identifier', 'product_id', 'location_id', 'serial_number', 'created_by', 'created_at'] as $key) {
            $this->assertSame($before[$key] ?? null, $unit->fresh()->getAttributes()[$key] ?? null);
        }
        $this->patch(route('products.status', $product), ['is_active' => 0, 'lock_version' => $product->lock_version])->assertSessionHasNoErrors();
        $this->put(route('individual-units.update', $unit), $this->individualPayload($unit->fresh(), ['notes' => 'Referencia conservada']))->assertSessionHasNoErrors();
        $this->patch(route('individual-units.status', $unit), ['is_active' => 1, 'lock_version' => $unit->fresh()->lock_version])->assertSessionHasErrors('product_id');
        $this->assertDatabaseCount('individual_units', 1);
    }

    public function test_real_assigned_units_even_inactive_prevent_link_and_location_history_changes(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $location = Location::factory()->create();
        $data = $this->individualData(['location_id' => $location->id]);
        $link = ProductLocation::factory()->create(['product_id' => $data['product_id'], 'location_id' => $location->id]);
        $unit = app(SaveIndividualUnit::class)->handle($actor, null, $data);
        $this->signIn($actor);
        foreach ([1, 0] as $active) {
            $unit->update(['is_active' => $active]);
            $this->patch(route('product-locations.status', [$unit->product, $link]), ['is_active' => 0, 'lock_version' => $unit->product->lock_version])->assertSessionHasErrors('is_active');
            $this->patch(route('locations.status', $location), ['is_active' => 0])->assertSessionHasErrors('is_active');
            $payload = array_intersect_key($location->getAttributes(), array_flip($location->getFillable()));
            $this->put(route('locations.update', $location), array_replace($payload, ['type' => 'cabinet']))->assertSessionHasErrors();
            $this->put(route('locations.update', $location), array_replace($payload, ['is_storable' => 0]))->assertSessionHasErrors();
        }
        // Inconsistent legacy status is simulated only in testing: the unit guard must still protect the location.
        $link->update(['is_active' => false]);
        $this->patch(route('locations.status', $location), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->patch(route('individual-units.status', $unit), ['is_active' => 1, 'lock_version' => $unit->lock_version])->assertSessionHasErrors('location_id');
        $this->assertSame($location->id, $unit->fresh()->location_id);
        $this->assertTrue($link->fresh()->exists);
    }

    public function test_failed_association_validation_rolls_back_metadata_and_version(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $location = Location::factory()->create();
        $data = $this->individualData(['location_id' => $location->id]);
        $link = ProductLocation::factory()->create(['product_id' => $data['product_id'], 'location_id' => $location->id]);
        $unit = app(SaveIndividualUnit::class)->handle($actor, null, $data);
        $link->update(['is_active' => false]);
        $before = $unit->fresh()->getAttributes();
        try {
            app(SaveIndividualUnit::class)->handle($actor, $unit, $this->individualPayload($unit, ['notes' => 'No guardar', 'serial_number' => 'Nueva']));
            $this->fail('Debe rechazar una asociación inactiva.');
        } catch (ValidationException) {
            $this->assertSame($before, $unit->fresh()->getAttributes());
        }
        $link->update(['is_active' => true]);
        // Force a failure AFTER INSERT to prove the complete action rolls back, including authors and identity.
        $listener = function () {
            throw new \RuntimeException('forced rollback');
        };
        IndividualUnit::created($listener);
        try {
            app(SaveIndividualUnit::class)->handle($actor, null, $this->individualData());
            $this->fail('Debe propagar el fallo.');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced rollback', $e->getMessage());
            $this->assertSame(1, DB::table('individual_units')->count());
        } finally {
            IndividualUnit::flushEventListeners();
        }
    }
}
