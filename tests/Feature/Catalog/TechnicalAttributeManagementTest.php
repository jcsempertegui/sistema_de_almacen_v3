<?php

namespace Tests\Feature\Catalog;

use App\Models\CatalogAttribute;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\User;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class TechnicalAttributeManagementTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public function test_admin_and_manager_create_edit_and_change_state_with_server_authors(): void
    {
        foreach ([1, 2] as $role) {
            $actor = User::factory()->create(['role_id' => $role]);
            $this->signIn($actor)->get(route('attributes.create'))->assertOk()->assertSee('Nuevo atributo técnico');
            $this->post(route('attributes.store'), $this->attributeData(['code' => ' attr-'.$role.' ', 'created_by' => 999, 'updated_by' => 999]))->assertRedirect(route('attributes.index'));
            $attribute = CatalogAttribute::where('code', 'ATTR-'.$role)->firstOrFail();
            $this->assertSame($actor->id, $attribute->creator->id);
            $this->assertSame($actor->id, $attribute->updater->id);
            $this->get(route('attributes.edit', $attribute))->assertOk();
            $this->put(route('attributes.update', $attribute), $this->attributeData(['code' => $attribute->code, 'name' => 'Nombre corregido']))->assertRedirect();
            $this->patch(route('attributes.status', $attribute), ['is_active' => 0, 'name' => 'Alterado'])->assertRedirect();
            $this->assertFalse($attribute->fresh()->is_active);
            $this->assertSame('Nombre corregido', $attribute->fresh()->name);
            $this->patch(route('attributes.status', $attribute), ['is_active' => 1])->assertRedirect();
            $this->assertTrue($attribute->fresh()->is_active);
        }
    }

    public function test_all_four_types_are_accepted_and_only_decimal_may_have_a_unit(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (array_keys(CatalogAttribute::TYPES) as $type) {
            $this->post(route('attributes.store'), $this->attributeData(['code' => $type, 'data_type' => $type, 'measurement_unit_id' => $type === 'decimal' ? $unit->id : null]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('attributes', ['code' => strtoupper($type), 'data_type' => $type]);
            if ($type !== 'decimal') {
                $this->post(route('attributes.store'), $this->attributeData(['code' => $type.'-UNIT', 'data_type' => $type, 'measurement_unit_id' => $unit->id]))->assertSessionHasErrors('measurement_unit_id');
            }
        }
        $this->assertSame(1, $unit->attributes()->count());
    }

    public function test_required_lengths_type_unit_and_state_are_validated_in_spanish(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (['code', 'name', 'data_type', 'is_active'] as $field) {
            $data = $this->attributeData();
            unset($data[$field]);
            $this->post(route('attributes.store'), $data)->assertSessionHasErrors($field);
        }
        foreach ([['code' => ' '], ['name' => ' '], ['code' => str_repeat('x', 51)], ['name' => str_repeat('x', 101)], ['help_text' => str_repeat('x', 256)], ['data_type' => 'Decimal'], ['data_type' => 'integer'], ['measurement_unit_id' => 999999], ['is_active' => 2]] as $changes) {
            $this->post(route('attributes.store'), $this->attributeData($changes))->assertSessionHasErrors();
        }
        $this->post(route('attributes.store'), $this->attributeData(['name' => '']))->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
        $this->assertDatabaseCount('attributes', 0);
    }

    public function test_duplicate_codes_are_rejected_even_when_inactive_but_names_may_repeat(): void
    {
        $attribute = $this->technicalAttribute(['code' => 'TEMP', 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('attributes.store'), $this->attributeData(['code' => ' temp ']))->assertSessionHasErrors('code');
        $this->post(route('attributes.store'), $this->attributeData(['code' => 'OTRA', 'name' => $attribute->name]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attributes', 2);
    }

    public function test_type_and_unit_can_change_before_association_and_creator_is_preserved(): void
    {
        $creator = User::factory()->create(['role_id' => 1]);
        $editor = User::factory()->create(['role_id' => 2]);
        $attribute = $this->technicalAttribute(['data_type' => 'text']);
        $attribute->created_by = $creator->id;
        $attribute->save();
        $unit = MeasurementUnit::factory()->create();
        $this->signIn($editor)->put(route('attributes.update', $attribute), $this->attributeData(['code' => $attribute->code, 'measurement_unit_id' => $unit->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('decimal', $attribute->fresh()->data_type);
        $this->assertSame($unit->id, $attribute->fresh()->measurement_unit_id);
        $this->assertSame($creator->id, $attribute->fresh()->created_by);
        $this->assertSame($editor->id, $attribute->fresh()->updated_by);
    }

    public function test_any_association_freezes_type_and_unit_even_if_inactive_and_rollback_preserves_metadata(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $attribute = $this->technicalAttribute(['measurement_unit_id' => $unit->id]);
        $association = $this->association(Category::factory()->create(), $attribute, ['is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach ([['data_type' => 'text', 'measurement_unit_id' => null], ['measurement_unit_id' => null]] as $changes) {
            $this->put(route('attributes.update', $attribute), $this->attributeData(array_replace(['code' => $attribute->code, 'name' => 'No persistir', 'measurement_unit_id' => $unit->id], $changes)))->assertSessionHasErrors('data_type');
            $this->assertSame('decimal', $attribute->fresh()->data_type);
            $this->assertSame($unit->id, $attribute->fresh()->measurement_unit_id);
            $this->assertNotSame('No persistir', $attribute->fresh()->name);
        }
        $this->put(route('attributes.update', $attribute), $this->attributeData(['code' => $attribute->code, 'measurement_unit_id' => $unit->id, 'name' => 'Nombre seguro']))->assertSessionHasNoErrors();
        $this->assertSame($attribute->id, $association->fresh()->attribute->id);
    }

    public function test_active_association_blocks_global_deactivation_from_both_endpoints(): void
    {
        $attribute = $this->technicalAttribute();
        $association = $this->association(Category::factory()->create(), $attribute);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->patch(route('attributes.status', $attribute), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put(route('attributes.update', $attribute), $this->attributeData(['code' => $attribute->code, 'is_active' => 0, 'name' => 'No persistir']))->assertSessionHasErrors('is_active');
        $this->assertTrue($attribute->fresh()->is_active);
        $this->patch(route('category-attributes.status', [$association->category, $association]), ['is_active' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('attributes.status', $attribute), ['is_active' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($attribute->fresh()->is_active);
        $this->assertDatabaseHas('category_attributes', ['id' => $association->id, 'attribute_id' => $attribute->id]);
        $this->delete('/atributos-tecnicos/'.$attribute->id)->assertStatus(405);
    }

    public function test_inactive_unit_blocks_new_attributes_and_activation_but_allows_historical_name_correction(): void
    {
        $unit = MeasurementUnit::factory()->create(['is_active' => false]);
        $attribute = $this->technicalAttribute(['measurement_unit_id' => $unit->id, 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('attributes.store'), $this->attributeData(['measurement_unit_id' => $unit->id]))->assertSessionHasErrors('measurement_unit_id');
        $this->patch(route('attributes.status', $attribute), ['is_active' => 1])->assertSessionHasErrors('measurement_unit_id');
        $this->put(route('attributes.update', $attribute), $this->attributeData(['code' => $attribute->code, 'measurement_unit_id' => $unit->id, 'is_active' => 0, 'name' => 'Corrección histórica']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Corrección histórica', $attribute->fresh()->name);
        $this->assertFalse($attribute->fresh()->is_active);
    }

    public function test_actual_attribute_references_protect_unit_state_and_semantics(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $attribute = $this->technicalAttribute(['measurement_unit_id' => $unit->id]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->patch(route('measurement-units.status', $unit), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->patch(route('attributes.status', $attribute), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->patch(route('measurement-units.status', $unit), ['is_active' => 0])->assertSessionHasNoErrors();
        foreach (['code' => 'OTRO', 'symbol' => 'XX', 'dimension' => $unit->dimension === 'other' ? 'length' : 'other', 'decimal_places' => $unit->decimal_places === 6 ? 0 : 6] as $field => $value) {
            $this->put(route('measurement-units.update', $unit), array_replace($unit->fresh()->only(['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active']), [$field => $value]))->assertSessionHasErrors('measurement_unit');
        }
        $this->put(route('measurement-units.update', $unit), array_replace($unit->fresh()->only(['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active']), ['name' => 'Unidad corregida']))->assertSessionHasNoErrors();
    }

    public function test_search_type_state_pagination_wildcards_and_escaped_help(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 3]));
        for ($i = 0; $i < 22; $i++) {
            $this->technicalAttribute(['name' => 'Filtro '.$i, 'data_type' => 'text', 'is_active' => false]);
        }
        $this->get(route('attributes.index', ['q' => 'Filtro', 'data_type' => 'text', 'status' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('data_type=text', false);
        $this->get(route('attributes.index', ['q' => 'Filtro', 'data_type' => 'date']))->assertSee('No se encontraron atributos.');
        $attribute = $this->technicalAttribute(['code' => 'ATTR%_', 'name' => '<script>alert(1)</script>', 'help_text' => '<img src=x onerror=alert(1)>']);
        $this->get(route('attributes.index', ['q' => '%_']))->assertSee($attribute->code)->assertSee('&lt;script&gt;', false)->assertDontSee('<img src=x', false)->assertDontSee('Filtro 0');
        $this->get(route('attributes.index', ['data_type' => 'wrong']))->assertSessionHasErrors('data_type');
    }
}
