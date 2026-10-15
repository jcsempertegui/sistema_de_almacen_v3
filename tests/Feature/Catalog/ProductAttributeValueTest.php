<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\User;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductAttributeValueTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_all_four_types_zero_false_negative_decimal_and_dates_are_saved_exactly(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $values = [];
        foreach (['text' => '0', 'decimal' => '-2.125', 'boolean' => '0', 'date' => '2024-02-29'] as $type => $raw) {
            $definition = $this->association($category, $this->technicalAttribute(['data_type' => $type]), ['is_required' => 1]);
            $values[$definition->id] = $raw;
        }
        $this->signIn($actor)->post(route('products.store'), $this->productData(['category_id' => $category->id, 'values' => $values]))->assertSessionHasNoErrors();
        $product = Product::firstOrFail();
        $this->assertCount(4, $product->attributeValues);
        $this->assertDatabaseHas('product_attribute_values', ['value_decimal' => '-2.125000']);
        $this->assertDatabaseHas('product_attribute_values', ['value_boolean' => 0]);
        $this->assertDatabaseCount('product_attribute_value_revisions', 4);
        $this->get(route('products.show', $product))->assertOk()->assertSee('Ver revisiones');
    }

    public function test_required_types_ranges_precision_and_category_membership_are_enforced(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $category = Category::factory()->create();
        $unit = MeasurementUnit::factory()->create(['decimal_places' => 2]);
        $definition = $this->association($category, $this->technicalAttribute(['measurement_unit_id' => $unit->id]), ['is_required' => 1, 'min_value' => '-5', 'max_value' => '5']);
        $data = $this->productData(['category_id' => $category->id]);
        foreach ([null, '', '5.01', '-5.01', '0.001', '1e2', '1,5', 1.5, ['x'], '1000000000000'] as $value) {
            $this->post(route('products.store'), array_replace($data, ['values' => [$definition->id => $value]]))->assertSessionHasErrors('values.'.$definition->id);
        }
        $other = $this->association(Category::factory()->create(), $this->technicalAttribute());
        $this->post(route('products.store'), array_replace($data, ['values' => [$other->id => '1']]))->assertSessionHasErrors('values');
        $this->post(route('products.store'), array_replace($data, ['values' => [999999 => '1']]))->assertSessionHasErrors('values');
        $this->post(route('products.store'), array_replace($data, ['values' => [$definition->id => '0.00']]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_attribute_values', ['value_decimal' => '0.000000']);
    }

    public function test_invalid_boolean_text_and_calendar_dates_are_rejected(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (['boolean' => ['yes', 2, []], 'text' => [false, str_repeat('a', 256), []], 'date' => ['2025-02-29', '2024-04-31', '0000-00-00', '15/10/2026']] as $type => $invalid) {
            $category = Category::factory()->create();
            $definition = $this->association($category, $this->technicalAttribute(['data_type' => $type]));
            $data = $this->productData(['category_id' => $category->id]);
            foreach ($invalid as $value) {
                $this->post(route('products.store'), array_replace($data, ['values' => [$definition->id => $value]]))->assertSessionHasErrors('values.'.$definition->id);
            }
        }
        $this->assertDatabaseCount('products', 0);
    }

    public function test_inactive_definitions_cannot_be_written_and_required_values_are_checked_on_reactivation(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']), ['is_required' => 1]);
        $product = $this->savedProduct($actor, ['category_id' => $category->id, 'is_active' => 0]);
        $this->signIn($actor)->patch(route('products.status', $product), ['is_active' => 1, 'lock_version' => 1])->assertSessionHasErrors('values.'.$definition->id);
        $definition->update(['is_active' => false]);
        $this->post(route('products.store'), $this->productData(['category_id' => $category->id, 'values' => [$definition->id => 'No']]))->assertSessionHasErrors('values');
    }
}
