<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveProduct;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\ProductAttributeValueRevision;
use App\Models\User;
use App\Services\ProductAttributeHistory;
use RuntimeException;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductHistoryTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_edit_clear_and_reactivate_preserve_all_revisions_and_one_value_identity(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']));
        $product = $this->savedProduct($actor, ['category_id' => $category->id, 'values' => [$definition->id => 'Anterior']]);
        $id = $product->attributeValues()->firstOrFail()->id;
        foreach (['Nuevo', null, 'Restaurado'] as $value) {
            $product = app(SaveProduct::class)->handle($actor, $product->fresh(), $this->productPayload($product->fresh(), ['values' => [$definition->id => $value]]));
        }
        $this->assertSame(1, $product->attributeValues()->count());
        $this->assertSame($id, $product->attributeValues()->firstOrFail()->id);
        $this->assertSame(['Anterior', 'Nuevo', 'Nuevo', 'Restaurado'], ProductAttributeValueRevision::orderBy('revision')->pluck('value_text')->all());
        $this->assertSame([true, true, false, true], ProductAttributeValueRevision::orderBy('revision')->get()->pluck('is_active')->all());
        $this->assertSame([1, 2, 3, 4], ProductAttributeValueRevision::orderBy('revision')->pluck('revision')->all());
        $this->assertSame(4, $product->lock_version);
        app(SaveProduct::class)->handle($actor, $product, $this->productPayload($product, ['values' => [$definition->id => 'Restaurado']]));
        $this->assertDatabaseCount('product_attribute_value_revisions', 4);
    }

    public function test_failure_after_first_value_and_revision_rolls_back_the_entire_product(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $values = [];
        foreach ([1, 2] as $n) {
            $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']));
            $values[$definition->id] = 'Valor '.$n;
        }
        $real = new ProductAttributeHistory;
        $calls = 0;
        $mock = \Mockery::mock(ProductAttributeHistory::class);
        $mock->shouldReceive('record')->twice()->andReturnUsing(function ($row, $operator) use ($real, &$calls) {
            if (++$calls === 2) {
                throw new RuntimeException('Fallo simulado de revisión');
            }
            $real->record($row, $operator);
        });
        $this->app->instance(ProductAttributeHistory::class, $mock);
        try {
            $this->savedProduct($actor, ['category_id' => $category->id, 'values' => $values]);
            $this->fail('Se esperaba rollback.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado de revisión', $e->getMessage());
        }
        foreach (['products', 'product_attribute_values', 'product_attribute_value_revisions', 'product_locations'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_historical_configuration_and_product_structure_cannot_be_reinterpreted(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']));
        $product = $this->savedProduct($actor, ['category_id' => $category->id, 'values' => [$definition->id => 'Dato']]);
        $this->signIn($actor);
        foreach (['category_id' => Category::factory()->create()->id, 'measurement_unit_id' => MeasurementUnit::factory()->create()->id, 'control_mode' => 'individual', 'is_loanable' => 1] as $field => $value) {
            $changes = [$field => $value];
            if ($field === 'is_loanable') {
                $changes['control_mode'] = 'individual';
            }
            $this->put(route('products.update', $product), $this->productPayload($product, $changes))->assertSessionHasErrors('category_id');
        }
        $definition->update(['is_active' => false]);
        $this->put(route('products.update', $product), $this->productPayload($product, ['name' => 'Metadatos corregidos']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_attribute_values', ['value_text' => 'Dato', 'is_active' => 1]);
        $this->put(route('category-attributes.update', [$category, $definition]), $this->associationData(['is_identity' => 1, 'is_active' => 0]))->assertSessionHasErrors('is_identity');
    }

    public function test_required_association_reactivation_is_checked_on_both_endpoints(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']), ['is_required' => 1, 'is_active' => 0]);
        $this->savedProduct($actor, ['category_id' => $category->id]);
        $this->signIn($actor)->patch(route('category-attributes.status', [$category, $definition]), ['is_active' => 1])->assertSessionHasErrors('is_required');
        $this->put(route('category-attributes.update', [$category, $definition]), $this->associationData(['is_required' => 1]))->assertSessionHasErrors('is_required');
        $this->assertFalse($definition->fresh()->is_active);
    }

    public function test_existing_category_and_unit_guards_now_detect_real_product_references(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $product = $this->savedProduct($actor);
        $this->signIn($actor)->patch(route('categories.status', $product->category), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->patch(route('measurement-units.status', $product->measurementUnit), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->assertTrue($product->category->fresh()->is_active);
        $this->assertTrue($product->measurementUnit->fresh()->is_active);
    }

    public function test_revisions_have_no_update_or_delete_application_path(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']));
        $this->savedProduct($actor, ['category_id' => $category->id, 'values' => [$definition->id => 'Anterior']]);
        $revision = ProductAttributeValueRevision::firstOrFail();
        try {
            $revision->update(['value_text' => 'Alterado']);
            $this->fail('Debe ser inmutable.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('product_attribute_value_revisions', ['id' => $revision->id, 'value_text' => 'Anterior']);
        }
        try {
            $revision->fresh()->delete();
            $this->fail('Debe conservarse.');
        } catch (\LogicException) {
            $this->assertDatabaseCount('product_attribute_value_revisions', 1);
        }
    }
}
