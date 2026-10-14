<?php

namespace Tests\Feature\Catalog;

use App\Models\MeasurementUnit;
use App\Models\User;
use App\Services\CatalogReferences;
use Tests\TestCase;

class MeasurementUnitManagementTest extends TestCase
{
    private function payload(array $changes = []): array
    {
        return array_replace(['code' => 'METRO', 'name' => 'Metro', 'symbol' => 'm', 'dimension' => 'length', 'decimal_places' => 3, 'is_active' => 1], $changes);
    }

    public function test_initial_examples_can_be_registered_by_administrator_and_manager(): void
    {
        $examples = [['UNIDAD', 'Unidad', 'unid.', 'count', 0], ['METRO', 'Metro', 'm', 'length', 3], ['KILO', 'Kilogramo', 'kg', 'mass', 6], ['LITRO', 'Litro', 'L', 'volume', 3]];
        foreach ($examples as $i => [$code, $name, $symbol, $dimension, $precision]) {
            $actor = User::factory()->create(['role_id' => $i % 2 + 1]);
            $this->signIn($actor)->get(route('measurement-units.create'))->assertOk()->assertSee('Precisión decimal');
            $this->post(route('measurement-units.store'), $this->payload(['code' => ' '.strtolower($code).' ', 'name' => $name, 'symbol' => $symbol, 'dimension' => $dimension, 'decimal_places' => $precision, 'created_by' => 999]))->assertRedirect(route('measurement-units.index'));
            $unit = MeasurementUnit::where('code', $code)->firstOrFail();
            $this->assertSame($symbol, $unit->symbol);
            $this->assertSame($precision, $unit->decimal_places);
            $this->assertSame($actor->id, $unit->creator->id);
        }
        $this->assertDatabaseCount('measurement_units', 4);
    }

    public function test_required_fields_precision_dimensions_and_lengths_are_validated(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 2]));
        foreach (['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active'] as $field) {
            $data = $this->payload();
            unset($data[$field]);
            $this->post(route('measurement-units.store'), $data)->assertSessionHasErrors($field);
        }
        foreach ([-1, 7, '1.5', 'abc'] as $precision) {
            $this->post(route('measurement-units.store'), $this->payload(['decimal_places' => $precision]))->assertSessionHasErrors('decimal_places');
        }
        foreach ([['dimension' => 'energy'], ['dimension' => ['length']], ['symbol' => '   '], ['code' => str_repeat('x', 31)], ['name' => str_repeat('x', 101)], ['symbol' => str_repeat('x', 21)], ['is_active' => 2]] as $changes) {
            $this->post(route('measurement-units.store'), $this->payload($changes))->assertSessionHasErrors();
        }
        $this->post(route('measurement-units.store'), $this->payload(['decimal_places' => 7]))->assertSessionHasErrors(['decimal_places' => 'La precisión decimal debe estar entre 0 y 6.']);
        $this->assertDatabaseCount('measurement_units', 0);
    }

    public function test_all_six_dimensions_are_accepted_without_conversions(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 2]));
        foreach (array_keys(MeasurementUnit::DIMENSIONS) as $dimension) {
            $this->post(route('measurement-units.store'), $this->payload(['code' => $dimension, 'symbol' => $dimension, 'dimension' => $dimension]))->assertRedirect();
        }
        $this->assertDatabaseCount('measurement_units', 6);
    }

    public function test_symbols_are_case_sensitive_trimmed_and_exact_duplicates_are_rejected(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('measurement-units.store'), $this->payload(['symbol' => ' m ']))->assertRedirect();
        $this->post(route('measurement-units.store'), $this->payload(['code' => 'DISTINTA', 'symbol' => 'M']))->assertRedirect();
        $this->post(route('measurement-units.store'), $this->payload(['code' => 'OTRA', 'symbol' => 'm']))->assertSessionHasErrors('symbol');
        $this->post(route('measurement-units.store'), $this->payload(['code' => ' metro ', 'symbol' => 'x']))->assertSessionHasErrors('code');
        $this->assertDatabaseCount('measurement_units', 2);
        $this->assertDatabaseHas('measurement_units', ['code' => 'METRO', 'symbol' => 'm']);
    }

    public function test_unit_can_be_edited_without_references_and_creator_is_preserved(): void
    {
        $creator = User::factory()->create(['role_id' => 1]);
        $editor = User::factory()->create(['role_id' => 2]);
        $unit = MeasurementUnit::factory()->create(['created_by' => $creator->id]);
        $this->signIn($editor)->get(route('measurement-units.edit', $unit))->assertOk();
        $this->put(route('measurement-units.update', $unit), $this->payload(['code' => 'EDITADA', 'name' => 'Unidad editada', 'symbol' => 'L', 'dimension' => 'volume', 'decimal_places' => 6, 'created_by' => $editor->id]))->assertRedirect();
        $fresh = $unit->fresh();
        $this->assertSame('EDITADA', $fresh->code);
        $this->assertSame('L', $fresh->symbol);
        $this->assertSame('volume', $fresh->dimension);
        $this->assertSame(6, $fresh->decimal_places);
        $this->assertSame($creator->id, $fresh->created_by);
        $this->assertSame($editor->id, $fresh->updater->id);
        $this->put(route('measurement-units.update', $unit), $this->payload(['code' => 'EDITADA', 'symbol' => 'L', 'dimension' => 'volume', 'decimal_places' => 6]))->assertRedirect();
    }

    public function test_used_unit_cannot_change_code_symbol_dimension_or_precision_but_name_can_change(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $data = $unit->only(['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active']);
        $this->partialMock(CatalogReferences::class)->shouldReceive('unitHasAnyReferences')->times(4)->with($unit->id)->andReturn(true);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach ([['code' => 'OTRA'], ['symbol' => 'L'], ['dimension' => 'volume'], ['decimal_places' => 1]] as $changes) {
            $this->put(route('measurement-units.update', $unit), array_replace($data, $changes))->assertSessionHasErrors('measurement_unit');
        }
        $this->assertSame($data, $unit->fresh()->only(array_keys($data)));
        $this->put(route('measurement-units.update', $unit), array_replace($data, ['name' => 'Nombre aclarado']))->assertRedirect();
        $this->assertSame('Nombre aclarado', $unit->fresh()->name);
    }

    public function test_active_references_block_status_patch_and_form_deactivation(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $this->partialMock(CatalogReferences::class)->shouldReceive('unitHasActiveReferences')->twice()->with($unit->id)->andReturn(true);
        $this->signIn(User::factory()->create(['role_id' => 2]));
        $this->patch(route('measurement-units.status', $unit), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put(route('measurement-units.update', $unit), array_replace($unit->only(['code', 'name', 'symbol', 'dimension', 'decimal_places']), ['is_active' => 0]))->assertSessionHasErrors('is_active');
        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_units_are_deactivated_reactivated_and_never_deleted_or_reused(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $this->signIn(User::factory()->create(['role_id' => 2]));
        $this->patch(route('measurement-units.status', $unit), ['is_active' => 0, 'decimal_places' => 6])->assertRedirect();
        $this->assertFalse($unit->fresh()->is_active);
        $this->assertSame(0, $unit->fresh()->decimal_places);
        $this->post(route('measurement-units.store'), $this->payload(['symbol' => $unit->symbol]))->assertSessionHasErrors('symbol');
        $this->patch(route('measurement-units.status', $unit), ['is_active' => 1])->assertRedirect();
        $this->delete('/unidades-de-medida/'.$unit->id)->assertStatus(405);
        $this->assertDatabaseHas('measurement_units', ['id' => $unit->id, 'is_active' => true]);
    }

    public function test_unit_search_status_and_pagination_are_consistent(): void
    {
        MeasurementUnit::factory()->count(22)->create(['name' => 'Coincidencia', 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 3]));
        $this->get(route('measurement-units.index', ['q' => 'Coincidencia', 'status' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('status=inactive', false);
        $this->get(route('measurement-units.index', ['status' => 'active']))->assertSee('No se encontraron unidades de medida.');
        $unit = MeasurementUnit::factory()->create(['name' => 'Identidad única', 'symbol' => 'LIT']);
        foreach ([$unit->code, $unit->name, $unit->symbol] as $search) {
            $this->get(route('measurement-units.index', ['q' => $search]))->assertOk()->assertSee('Identidad única');
        }
        $this->get(route('measurement-units.index', ['status' => 'wrong']))->assertSessionHasErrors('status');
    }

    public function test_duplicate_unit_updates_are_rejected_without_partial_changes(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $other = MeasurementUnit::factory()->create(['is_active' => false]);
        $data = $unit->only(['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active']);
        $this->signIn(User::factory()->create(['role_id' => 2]));
        $this->put(route('measurement-units.update', $unit), array_replace($data, ['code' => $other->code, 'name' => 'No persistir']))->assertSessionHasErrors('code');
        $this->put(route('measurement-units.update', $unit), array_replace($data, ['symbol' => $other->symbol]))->assertSessionHasErrors('symbol');
        $this->assertSame($data, $unit->fresh()->only(array_keys($data)));
    }

    public function test_unit_names_and_symbols_are_escaped_in_blade(): void
    {
        MeasurementUnit::factory()->create(['name' => '<script>alert(1)</script>', 'symbol' => '<b>X</b>']);
        $this->signIn(User::factory()->create(['role_id' => 3]))->get(route('measurement-units.index'))->assertOk()->assertSee('&lt;script&gt;', false)->assertSee('&lt;b&gt;X&lt;/b&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>X</b>', false);
    }
}
