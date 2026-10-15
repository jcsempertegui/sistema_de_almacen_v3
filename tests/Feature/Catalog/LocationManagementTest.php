<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveLocation;
use App\Models\Location;
use App\Models\User;
use App\Services\LocationHierarchy;
use App\Services\LocationReferences;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    private function payload(array $changes = []): array
    {
        return array_replace(['code' => 'SEC-A', 'name' => 'Sector A', 'type' => 'sector', 'parent_id' => null, 'is_storable' => 1, 'description' => 'Área de almacenamiento', 'is_active' => 1], $changes);
    }

    public function test_administrator_and_manager_create_edit_and_toggle_with_server_authors(): void
    {
        foreach ([1, 2] as $role) {
            $actor = User::factory()->create(['role_id' => $role]);
            $this->signIn($actor)->get(route('locations.create'))->assertOk()->assertSee('Nueva ubicación');
            $this->post(route('locations.store'), $this->payload(['code' => ' sec-'.$role.' ', 'created_by' => 999, 'updated_by' => 999]))->assertRedirect(route('locations.index'))->assertSessionHasNoErrors();
            $location = Location::where('code', 'SEC-'.$role)->firstOrFail();
            $this->assertSame($actor->id, $location->creator->id);
            $this->assertSame($actor->id, $location->updater->id);
            $this->get(route('locations.edit', $location))->assertOk();
            $this->put(route('locations.update', $location), $this->payload(['code' => $location->code, 'name' => 'Nombre corregido', 'is_storable' => 0]))->assertSessionHasNoErrors();
            $this->patch(route('locations.status', $location), ['is_active' => 0, 'code' => 'ALTERADO', 'is_storable' => 1])->assertSessionHasNoErrors();
            $this->assertFalse($location->fresh()->is_active);
            $this->assertFalse($location->fresh()->is_storable);
            $this->assertSame('Nombre corregido', $location->fresh()->name);
            $this->patch(route('locations.status', $location), ['is_active' => 1])->assertSessionHasNoErrors();
            $this->assertTrue($location->fresh()->is_active);
            $this->delete('/ubicaciones/'.$location->id)->assertStatus(405);
            $this->assertDatabaseHas('locations', ['id' => $location->id, 'code' => 'SEC-'.$role]);
        }
    }

    public function test_three_types_hierarchy_and_non_storable_parent_are_supported(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $parent = null;
        foreach (Location::TYPES as $type => $label) {
            $this->post(route('locations.store'), $this->payload(['code' => $type, 'name' => $label, 'type' => $type, 'parent_id' => $parent?->id, 'is_storable' => 0]))->assertSessionHasNoErrors();
            $node = Location::where('code', strtoupper($type))->firstOrFail();
            $this->assertSame($parent?->id, $node->parent_id);
            if ($parent) {
                $this->assertSame($parent->id, $node->parent->id);
                $this->assertSame($node->id, $parent->children()->firstOrFail()->id);
            }
            $parent = $node;
        }
        $this->get(route('locations.tree'))->assertOk()->assertSeeInOrder(['SECTOR · Sector', 'SHELF · Estante', 'CABINET · Gabinete'])->assertSee('SECTOR · Sector → SHELF · Estante → CABINET · Gabinete');
    }

    public function test_fields_types_flags_and_lengths_are_validated_in_spanish(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (['code', 'name', 'type', 'is_storable', 'is_active'] as $field) {
            $data = $this->payload();
            unset($data[$field]);
            $this->post(route('locations.store'), $data)->assertSessionHasErrors($field);
        }
        foreach ([['code' => '  '], ['name' => '  '], ['code' => str_repeat('x', 61)], ['name' => str_repeat('x', 151)], ['description' => str_repeat('x', 10001)], ['type' => 'Sector'], ['type' => 'aisle'], ['parent_id' => 999999], ['is_storable' => 2], ['is_active' => 2]] as $changes) {
            $this->post(route('locations.store'), $this->payload($changes))->assertSessionHasErrors();
        }
        $this->post(route('locations.store'), $this->payload(['name' => '']))->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
        $this->assertDatabaseCount('locations', 0);
    }

    public function test_inactive_code_is_not_reused_but_names_may_repeat(): void
    {
        $location = Location::factory()->create(['code' => 'SEC-A', 'name' => 'Repetido', 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('locations.store'), $this->payload(['code' => ' sec-a ']))->assertSessionHasErrors('code');
        $this->post(route('locations.store'), $this->payload(['code' => 'OTRO', 'name' => 'Repetido']))->assertSessionHasNoErrors();
        $this->put(route('locations.update', $location), $this->payload(['code' => $location->code, 'name' => 'Repetido', 'is_active' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('locations', 2);
    }

    public function test_self_and_descendants_including_inactive_nodes_are_rejected_without_partial_edits(): void
    {
        $root = Location::factory()->create();
        $child = Location::factory()->create(['parent_id' => $root->id, 'is_active' => false]);
        $grandchild = Location::factory()->create(['parent_id' => $child->id, 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach ([$root, $child, $grandchild] as $parent) {
            $this->put(route('locations.update', $root), $this->payload(['code' => $root->code, 'parent_id' => $parent->id, 'name' => 'No persistir']))->assertSessionHasErrors('parent_id');
            $this->assertNull($root->fresh()->parent_id);
            $this->assertNotSame('No persistir', $root->fresh()->name);
        }
    }

    public function test_inactive_parents_ancestors_and_reactivation_are_checked_with_historical_exception(): void
    {
        $root = Location::factory()->create(['is_active' => false]);
        $child = Location::factory()->create(['parent_id' => $root->id, 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('locations.store'), $this->payload(['parent_id' => $root->id, 'is_active' => 0]))->assertSessionHasErrors('parent_id');
        $this->patch(route('locations.status', $child), ['is_active' => 1])->assertSessionHasErrors('parent_id');
        $this->put(route('locations.update', $child), $this->payload(['code' => $child->code, 'parent_id' => $root->id, 'is_active' => 0, 'name' => 'Corrección histórica']))->assertSessionHasNoErrors();
        $this->get(route('locations.edit', $child))->assertOk()->assertSee('Padre histórico inactivo');
        $this->assertSame($root->id, $child->fresh()->parent_id);
        // Simulated pre-existing inconsistency: an active parent under an inactive ancestor.
        $child->is_active = true;
        $child->save();
        $this->post(route('locations.store'), $this->payload(['parent_id' => $child->id]))->assertSessionHasErrors('parent_id');
        $this->patch(route('locations.status', $child), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->patch(route('locations.status', $root), ['is_active' => 1])->assertSessionHasNoErrors();
        $this->patch(route('locations.status', $child), ['is_active' => 1])->assertSessionHasNoErrors();
    }

    public function test_active_children_block_both_deactivation_endpoints_and_inactive_links_are_kept(): void
    {
        $root = Location::factory()->create();
        $child = Location::factory()->create(['parent_id' => $root->id]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->patch(route('locations.status', $root), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put(route('locations.update', $root), $this->payload(['code' => $root->code, 'is_active' => 0, 'name' => 'No persistir']))->assertSessionHasErrors('is_active');
        $this->assertTrue($root->fresh()->is_active);
        $this->patch(route('locations.status', $child), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->patch(route('locations.status', $root), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertSame($root->id, $child->fresh()->parent_id);
        $this->assertFalse($root->fresh()->is_active);
    }

    public function test_parent_options_exclude_self_and_descendants_and_omit_inactive_locations(): void
    {
        $root = Location::factory()->create(['name' => 'Propia']);
        Location::factory()->create(['parent_id' => $root->id, 'name' => 'Descendiente']);
        Location::factory()->create(['name' => 'Inactiva ajena', 'is_active' => false]);
        $allowed = Location::factory()->create(['name' => 'Padre permitido']);
        $response = $this->signIn(User::factory()->create(['role_id' => 1]))->get(route('locations.edit', $root))->assertOk();
        $response->assertSee('Padre permitido')->assertDontSee('Descendiente')->assertDontSee('Inactiva ajena');
        $response->assertDontSee('<option value="'.$root->id.'"', false);
        $this->put(route('locations.update', $root), $this->payload(['code' => $root->code, 'parent_id' => $allowed->id]))->assertSessionHasNoErrors();
        $this->assertSame($allowed->id, $root->fresh()->parent_id);
    }

    public function test_future_reference_guards_are_explicitly_simulated_and_rollback_changes(): void
    {
        $root = Location::factory()->create();
        $other = Location::factory()->create();
        $child = Location::factory()->create(['parent_id' => $root->id, 'is_active' => false]);
        $references = $this->partialMock(LocationReferences::class);
        $references->shouldReceive('hasAssignedUnits')->once()->with([$root->id, $child->id])->andReturn(true);
        $references->shouldReceive('hasHistoricalReferences')->twice()->with([$root->id, $child->id])->andReturn(true);
        $references->shouldReceive('hasHistoricalReferences')->once()->with([$root->id])->andReturn(true);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->patch(route('locations.status', $root), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put(route('locations.update', $root), $this->payload(['code' => $root->code, 'parent_id' => $other->id]))->assertSessionHasErrors('location');
        $this->put(route('locations.update', $root), $this->payload(['code' => $root->code, 'type' => 'cabinet']))->assertSessionHasErrors('location');
        $this->put(route('locations.update', $root), $this->payload(['code' => $root->code, 'is_storable' => 0]))->assertSessionHasErrors('is_storable');
        $this->assertTrue($root->fresh()->is_active);
        $this->assertTrue($root->fresh()->is_storable);
        $this->assertNull($root->fresh()->parent_id);
        $this->assertSame('sector', $root->fresh()->type);
    }

    public function test_search_filters_pagination_literal_wildcards_and_html_escape(): void
    {
        $parent = Location::factory()->create();
        for ($i = 0; $i < 22; $i++) {
            Location::factory()->create(['parent_id' => $parent->id, 'name' => 'Filtro '.$i, 'type' => 'shelf', 'is_active' => false]);
        }
        $this->signIn(User::factory()->create(['role_id' => 3]));
        $this->get(route('locations.index', ['q' => 'Filtro', 'parent_id' => $parent->id, 'type' => 'shelf', 'status' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('type=shelf', false);
        $this->get(route('locations.index', ['q' => 'Filtro', 'roots_only' => 1]))->assertSee('No se encontraron ubicaciones.');
        foreach ([['type' => 'bad'], ['status' => 'bad'], ['parent_id' => 999999], ['q' => str_repeat('x', 101)], ['roots_only' => 2]] as $query) {
            $this->get(route('locations.index', $query))->assertSessionHasErrors();
        }
        $node = Location::factory()->create(['code' => 'LOC%_', 'name' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>']);
        $this->get(route('locations.index', ['q' => '%_']))->assertSee($node->code)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Filtro 0');
        $this->get(route('locations.tree'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_stale_action_inputs_are_rechecked_and_duplicate_collision_is_translated(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $root = Location::factory()->create();
        Location::whereKey($root->id)->update(['is_active' => false]);
        try {
            app(SaveLocation::class)->handle($actor, null, $this->payload(['parent_id' => $root->id]));
            $this->fail('La acción debe volver a consultar el padre.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        app(SaveLocation::class)->handle($actor, null, $this->payload(['code' => $root->code]));
    }

    public function test_existing_cycle_is_reported_without_infinite_traversal_or_partial_write(): void
    {
        $a = Location::factory()->create();
        $b = Location::factory()->create(['parent_id' => $a->id]);
        Location::whereKey($a->id)->update(['parent_id' => $b->id]);
        foreach ([fn () => app(LocationHierarchy::class)->paths(app(LocationHierarchy::class)->catalog()), fn () => app(LocationHierarchy::class)->subtreeIds($a->id)] as $operation) {
            try {
                $operation();
                $this->fail('Debe detectarse la inconsistencia existente.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('parent_id', $exception->errors());
            }
        }
        $this->expectException(ValidationException::class);
        app(SaveLocation::class)->handle(User::factory()->create(['role_id' => 1]), $a, ['name' => 'No persistir']);
    }
}
