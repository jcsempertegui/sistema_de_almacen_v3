<?php

namespace Tests\Feature\Catalog;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\TestCase;

class IndividualUnitManagementTest extends TestCase
{
    use CreatesIndividualUnitCatalog;

    public function test_managers_register_search_edit_and_toggle_without_stock_side_effects(): void
    {
        foreach ([1, 2] as $role) {
            $actor = User::factory()->create(['role_id' => $role]);
            $data = $this->individualData(['identifier' => ' ind-'.$role.' ', 'serial_number' => ' Serie-'.$role.' ']);
            $location = Location::factory()->create();
            ProductLocation::factory()->create(['product_id' => $data['product_id'], 'location_id' => $location->id]);
            $data['location_id'] = $location->id;
            $this->signIn($actor)->get(route('individual-units.create', ['product_id' => $data['product_id']]))->assertOk()->assertSee('Nueva unidad individualizada');
            $this->post(route('individual-units.store'), $data + ['created_by' => 999999, 'updated_by' => 999999, 'lock_version' => 999])->assertSessionHasNoErrors();
            $unit = IndividualUnit::where('identifier', 'IND-'.$role)->firstOrFail();
            $this->assertSame($actor->id, $unit->created_by);
            $this->assertSame(1, $unit->lock_version);
            $this->assertSame('Serie-'.$role, $unit->serial_number);
            $this->assertCount(1, $unit->product->individualUnits);
            $this->assertCount(1, $unit->location->individualUnits);
            $this->get(route('individual-units.index', ['q' => 'Serie-'.$role, 'product_id' => $unit->product_id, 'location_id' => $location->id, 'status' => 'active']))->assertOk()->assertSee($unit->identifier);
            $this->get(route('individual-units.index', ['q' => $unit->identifier]))->assertOk()->assertSee($unit->identifier);
            $this->get(route('individual-units.show', $unit))->assertOk()->assertSee('Sin observaciones');
            $this->get(route('individual-units.edit', $unit))->assertOk();
            $this->put(route('individual-units.update', $unit), $this->individualPayload($unit, ['notes' => '<script>test</script>']))->assertSessionHasNoErrors();
            $this->get(route('individual-units.show', $unit))->assertSee('&lt;script&gt;test&lt;/script&gt;', false)->assertDontSee('<script>test</script>', false);
            foreach ([0, 1] as $state) {
                $this->patch(route('individual-units.status', $unit), ['is_active' => $state, 'lock_version' => $unit->fresh()->lock_version])->assertSessionHasNoErrors();
                $this->assertSame((bool) $state, $unit->fresh()->is_active);
            }
            $this->delete(route('individual-units.show', $unit))->assertStatus(405);
        }
        foreach (['stocks', 'movements', 'loans'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_required_fields_types_lengths_and_product_mode_are_enforced(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $data = $this->individualData();
        foreach (['identifier', 'product_id', 'is_active'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post(route('individual-units.store'), $invalid)->assertSessionHasErrors($field);
        }
        foreach ([['identifier' => ' '], ['identifier' => str_repeat('A', 65)], ['serial_number' => str_repeat('S', 101)], ['serial_number' => []], ['notes' => str_repeat('N', 10001)], ['is_active' => 2], ['product_id' => 999999], ['location_id' => 999999]] as $changes) {
            $this->post(route('individual-units.store'), array_replace($data, $changes))->assertSessionHasErrors();
        }
        foreach ([['control_mode' => 'quantity'], ['control_mode' => 'individual', 'is_active' => false]] as $state) {
            $this->post(route('individual-units.store'), array_replace($data, ['product_id' => Product::factory()->create($state)->id]))->assertSessionHasErrors('product_id');
        }
        $this->assertDatabaseCount('individual_units', 0);
        $this->get(route('individual-units.index', ['status' => 'other']))->assertSessionHasErrors('status');
    }

    public function test_series_are_optional_case_sensitive_unique_per_product_and_zero_is_not_empty(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $this->signIn($actor);
        $base = $this->individualData();
        $index = 0;
        foreach ([null, '', '  ', 'S-a', 'S-A', '0'] as $serial) {
            $this->post(route('individual-units.store'), array_replace($base, ['identifier' => 'SERIES-'.chr(65 + $index++), 'serial_number' => $serial]))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('individual_units', 6);
        $this->assertSame(3, IndividualUnit::whereNull('serial_number')->count());
        $this->assertSame(1, IndividualUnit::where('serial_number', '0')->count());
        $this->get(route('individual-units.index', ['q' => '0']))->assertViewHas('units', fn ($units) => $units->total() === 1);
        $unit = IndividualUnit::where('serial_number', 'S-a')->firstOrFail();
        $this->patch(route('individual-units.status', $unit), ['is_active' => 0, 'lock_version' => 1])->assertSessionHasNoErrors();
        $this->post(route('individual-units.store'), array_replace($base, ['identifier' => 'OTHER', 'serial_number' => 'S-a']))->assertSessionHasErrors('serial_number');
        $this->post(route('individual-units.store'), $this->individualData(['serial_number' => 'S-a']))->assertSessionHasNoErrors();
        $this->post(route('individual-units.store'), array_replace($base, ['identifier' => strtolower($unit->identifier)]))->assertSessionHasErrors('identifier');
    }

    public function test_initial_location_requires_active_storable_location_and_matching_active_link(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $data = $this->individualData();
        foreach ([[], ['is_active' => false], ['is_storable' => false]] as $state) {
            $location = Location::factory()->create($state);
            if ($state) {
                ProductLocation::factory()->create(['product_id' => $data['product_id'], 'location_id' => $location->id]);
            }
            $this->post(route('individual-units.store'), array_replace($data, ['location_id' => $location->id]))->assertSessionHasErrors('location_id');
        }
        $location = Location::factory()->create();
        ProductLocation::factory()->create(['product_id' => $data['product_id'], 'location_id' => $location->id, 'is_active' => false]);
        $this->post(route('individual-units.store'), array_replace($data, ['location_id' => $location->id]))->assertSessionHasErrors('location_id');
        $other = Location::factory()->create();
        ProductLocation::factory()->create(['location_id' => $other->id]);
        $this->post(route('individual-units.store'), array_replace($data, ['location_id' => $other->id]))->assertSessionHasErrors('location_id');
        $parent = Location::factory()->create(['is_active' => false]);
        $child = Location::factory()->create(['parent_id' => $parent->id]);
        ProductLocation::factory()->create(['product_id' => $data['product_id'], 'location_id' => $child->id]);
        $this->post(route('individual-units.store'), array_replace($data, ['location_id' => $child->id]))->assertSessionHasErrors();
        $this->assertDatabaseCount('individual_units', 0);
    }

    public function test_identity_product_and_initial_location_are_fixed_and_stale_edits_fail(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $unit = $this->savedIndividual($actor);
        $this->signIn($actor);
        foreach ([['identifier' => 'NEW'], ['product_id' => Product::factory()->create(['control_mode' => 'individual'])->id], ['location_id' => Location::factory()->create()->id]] as $changes) {
            $this->put(route('individual-units.update', $unit), $this->individualPayload($unit, $changes))->assertSessionHasErrors(array_key_first($changes));
        }
        $this->put(route('individual-units.update', $unit), $this->individualPayload($unit, ['serial_number' => 'S-1', 'notes' => 'Primera']))->assertSessionHasNoErrors();
        $this->put(route('individual-units.update', $unit), $this->individualPayload($unit, ['notes' => 'Obsoleta']))->assertStatus(409);
        $unit->refresh();
        foreach ([null, 'S-2'] as $serial) {
            $this->put(route('individual-units.update', $unit), $this->individualPayload($unit, ['serial_number' => $serial]))->assertSessionHasErrors('serial_number');
        }
        $this->assertSame('Primera', $unit->fresh()->notes);
        $this->patch(route('individual-units.status', $unit), ['is_active' => 0])->assertSessionHasErrors('lock_version');
    }
}
