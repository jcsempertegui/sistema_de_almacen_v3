<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\User;
use App\Services\AttributeReferences;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class CategoryAttributeManagementTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public function test_admin_and_manager_associate_edit_and_toggle_without_changing_identity_or_authors(): void
    {
        $attribute = $this->technicalAttribute();
        foreach ([1, 2] as $role) {
            $actor = User::factory()->create(['role_id' => $role]);
            $category = Category::factory()->create();
            $this->signIn($actor)->get(route('category-attributes.create', $category))->assertOk();
            $this->post(route('category-attributes.store', $category), $this->associationData(['attribute_id' => $attribute->id, 'is_required' => 1, 'is_identity' => 1, 'display_order' => 65535, 'min_value' => '-10.500000', 'max_value' => '0', 'created_by' => 999]))->assertRedirect()->assertSessionHasNoErrors();
            $association = $category->categoryAttributes()->firstOrFail();
            $this->assertSame($actor->id, $association->creator->id);
            $this->assertSame($attribute->id, $association->attribute->id);
            $this->assertSame('-10.500000', $association->min_value);
            $this->assertSame('0.000000', $association->max_value);
            $this->get(route('category-attributes.edit', [$category, $association]))->assertOk();
            $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['display_order' => 3]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(3, $association->fresh()->display_order);
            $this->patch(route('category-attributes.status', [$category, $association]), ['is_active' => 0])->assertSessionHasNoErrors();
            $this->assertFalse($association->fresh()->is_active);
            $this->patch(route('category-attributes.status', [$category, $association]), ['is_active' => 1])->assertSessionHasNoErrors();
            $this->assertTrue($association->fresh()->is_active);
            $this->assertSame($actor->id, $association->fresh()->updater->id);
            $this->delete('/categorias/'.$category->id.'/atributos/'.$association->id)->assertStatus(405);
        }
    }

    public function test_duplicate_associations_are_rejected_even_when_inactive_and_picker_excludes_them(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute(['name' => 'Ya asociado']);
        $this->association($category, $attribute, ['is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('category-attributes.store', $category), $this->associationData(['attribute_id' => $attribute->id]))->assertSessionHasErrors('attribute_id');
        $this->get(route('category-attributes.create', $category))->assertDontSee('Ya asociado');
        $this->assertDatabaseCount('category_attributes', 1);
    }

    public function test_wrong_parent_returns_404_for_edit_update_and_state_and_parent_input_is_prohibited(): void
    {
        $category = Category::factory()->create();
        $wrong = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $association = $this->association($category, $attribute);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->get(route('category-attributes.edit', [$wrong, $association]))->assertNotFound();
        $this->put(route('category-attributes.update', [$wrong, $association]), $this->associationData())->assertNotFound();
        $this->patch(route('category-attributes.status', [$wrong, $association]), ['is_active' => 0])->assertNotFound();
        $this->post(route('category-attributes.store', $wrong), $this->associationData(['attribute_id' => $attribute->id, 'category_id' => $category->id]))->assertSessionHasErrors('category_id');
        $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['attribute_id' => $attribute->id]))->assertSessionHasErrors('attribute_id');
        $this->assertSame($category->id, $association->fresh()->category_id);
        $this->assertTrue($association->fresh()->is_active);
    }

    public function test_required_flags_order_and_attribute_references_are_validated(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $data = $this->associationData(['attribute_id' => $attribute->id]);
        foreach (['attribute_id', 'is_required', 'is_identity', 'display_order', 'is_active'] as $field) {
            $missing = $data;
            unset($missing[$field]);
            $this->post(route('category-attributes.store', $category), $missing)->assertSessionHasErrors($field);
        }
        foreach ([['attribute_id' => 999999], ['is_required' => 2], ['is_identity' => 2], ['is_active' => 2], ['display_order' => -1], ['display_order' => 65536], ['display_order' => '1.5']] as $changes) {
            $this->post(route('category-attributes.store', $category), array_replace($data, $changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('category_attributes', 0);
    }

    public function test_decimal_format_range_and_order_are_exact_and_do_not_round_invalid_values(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $data = $this->associationData(['attribute_id' => $attribute->id]);
        foreach (['1e3', '1,5', '1.0000001', '1000000000000', 'NaN', '1 000', ['1'], 0.5] as $value) {
            $this->postJson(route('category-attributes.store', $category), array_replace($data, ['min_value' => $value]))->assertUnprocessable()->assertJsonValidationErrors('min_value');
        }
        foreach ([['min_value' => '0.000002', 'max_value' => '0.000001'], ['min_value' => '-0.000001', 'max_value' => '-0.000002'], ['min_value' => '999999999999.999999', 'max_value' => '999999999999.999998']] as $changes) {
            $this->postJson(route('category-attributes.store', $category), array_replace($data, $changes))->assertUnprocessable()->assertJsonValidationErrors('max_value');
        }
        $this->post(route('category-attributes.store', $category), array_replace($data, ['min_value' => '-999999999999.999999', 'max_value' => '999999999999.999999']))->assertSessionHasNoErrors();
        $association = $category->categoryAttributes()->firstOrFail();
        $this->assertSame('-999999999999.999999', $association->min_value);
        $this->assertSame('999999999999.999999', $association->max_value);
    }

    public function test_unit_precision_zero_accepts_padded_integers_but_rejects_significant_fraction(): void
    {
        $category = Category::factory()->create();
        $unit = MeasurementUnit::factory()->create(['decimal_places' => 0]);
        $attribute = $this->technicalAttribute(['measurement_unit_id' => $unit->id]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $data = $this->associationData(['attribute_id' => $attribute->id, 'min_value' => '1.000000', 'max_value' => '2.000000']);
        $this->post(route('category-attributes.store', $category), array_replace($data, ['min_value' => '1.1']))->assertSessionHasErrors('min_value');
        $this->post(route('category-attributes.store', $category), $data)->assertSessionHasNoErrors();
        $association = $category->categoryAttributes()->firstOrFail();
        $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['min_value' => $association->min_value, 'max_value' => $association->max_value, 'display_order' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('1.000000', $association->fresh()->min_value);
    }

    public function test_non_decimal_types_reject_any_bound_including_zero(): void
    {
        $category = Category::factory()->create();
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (['text', 'boolean', 'date'] as $type) {
            $attribute = $this->technicalAttribute(['data_type' => $type]);
            foreach ([['min_value' => '0'], ['max_value' => '0']] as $changes) {
                $this->post(route('category-attributes.store', $category), $this->associationData(array_replace(['attribute_id' => $attribute->id], $changes)))->assertSessionHasErrors('min_value');
            }
        }
        $this->assertDatabaseCount('category_attributes', 0);
    }

    public function test_inactive_category_attribute_or_unit_prevents_new_or_reactivated_associations(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $association = $this->association($category, $attribute, ['is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach ([$category, $attribute] as $reference) {
            $reference->is_active = false;
            $reference->save();
            $this->patch(route('category-attributes.status', [$category, $association]), ['is_active' => 1])->assertSessionHasErrors('attribute_id');
            $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['is_active' => 1]))->assertSessionHasErrors('attribute_id');
            $otherCategory = $reference === $category ? $category : Category::factory()->create();
            $otherAttribute = $reference === $category ? $this->technicalAttribute() : $attribute;
            $this->post(route('category-attributes.store', $otherCategory), $this->associationData(['attribute_id' => $otherAttribute->id]))->assertSessionHasErrors('attribute_id');
            $reference->is_active = true;
            $reference->save();
        }
        $unit = MeasurementUnit::factory()->create(['is_active' => false]);
        $attribute->measurement_unit_id = $unit->id;
        $attribute->save();
        $this->patch(route('category-attributes.status', [$category, $association]), ['is_active' => 1])->assertSessionHasErrors('attribute_id');
        $this->assertFalse($association->fresh()->is_active);
    }

    public function test_historical_impact_guards_with_simulated_future_dependencies_rollback_all_changes(): void
    {
        $category = Category::factory()->create();
        $association = $this->association($category, $this->technicalAttribute(), ['min_value' => '-1']);
        $references = $this->partialMock(AttributeReferences::class);
        $references->shouldReceive('associationHasValues')->twice()->with($association->id)->andReturn(true);
        $references->shouldReceive('categoryHasIncompleteProducts')->once()->andReturn(true);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['is_identity' => 1, 'min_value' => '-1', 'display_order' => 7]))->assertSessionHasErrors('is_identity');
        $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['min_value' => '-2']))->assertSessionHasErrors('is_identity');
        $this->put(route('category-attributes.update', [$category, $association]), $this->associationData(['min_value' => '-1', 'is_required' => 1]))->assertSessionHasErrors('is_required');
        $this->assertFalse($association->fresh()->is_identity);
        $this->assertFalse($association->fresh()->is_required);
        $this->assertSame(0, $association->fresh()->display_order);
        $this->assertSame('-1.000000', $association->fresh()->min_value);
    }

    public function test_list_is_scoped_ordered_filtered_and_preserves_inactive_configuration(): void
    {
        $category = Category::factory()->create();
        $other = Category::factory()->create();
        $first = $this->association($category, $this->technicalAttribute(['name' => 'Primero']), ['display_order' => 1]);
        $this->association($category, $this->technicalAttribute(['name' => 'Segundo']), ['display_order' => 2, 'is_active' => false]);
        $this->association($other, $this->technicalAttribute(['name' => 'Ajeno']));
        $this->signIn(User::factory()->create(['role_id' => 3]));
        $this->get(route('category-attributes.index', $category))->assertOk()->assertSeeInOrder(['Primero', 'Segundo'])->assertDontSee('Ajeno')->assertDontSee('Editar');
        $this->get(route('category-attributes.index', [$category, 'status' => 'inactive']))->assertSee('Segundo')->assertDontSee('Primero');
        $this->signIn(User::factory()->create(['role_id' => 1]))->patch(route('categories.status', $category), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->is_active);
        $this->get(route('category-attributes.index', $category))->assertOk()->assertSee('Primero');
        $this->patch(route('category-attributes.status', [$category, $first]), ['is_active' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('category_attributes', ['id' => $first->id, 'category_id' => $category->id]);
    }
}
