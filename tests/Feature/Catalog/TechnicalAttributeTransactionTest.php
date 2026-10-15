<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveCatalogAttribute;
use App\Actions\Catalog\SaveCategoryAttribute;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class TechnicalAttributeTransactionTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public function test_duplicate_definitions_and_associations_are_translated_when_collision_reaches_the_action(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $attribute = $this->technicalAttribute(['code' => 'TEMP']);
        $category = Category::factory()->create();
        $this->association($category, $attribute);
        foreach ([
            ['code', fn () => app(SaveCatalogAttribute::class)->handle($actor, null, $this->attributeData())],
            ['attribute_id', fn () => app(SaveCategoryAttribute::class)->handle($actor, $category, null, $this->associationData(['attribute_id' => $attribute->id]))],
        ] as [$field, $operation]) {
            try {
                $operation();
                $this->fail('La colisión SQL debe convertirse en validación.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
                $this->assertDatabaseCount('attributes', 1);
                $this->assertDatabaseCount('category_attributes', 1);
            }
        }
    }

    public function test_action_rechecks_associations_added_after_the_attribute_was_loaded(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $attribute = $this->technicalAttribute();
        $this->association(Category::factory()->create(), $attribute, ['is_active' => false]);
        try {
            app(SaveCatalogAttribute::class)->handle($actor, $attribute, ['data_type' => 'text', 'name' => 'No persistir']);
            $this->fail('La acción debe verificar las asociaciones actuales.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data_type', $exception->errors());
            $this->assertSame('decimal', $attribute->fresh()->data_type);
            $this->assertNotSame('No persistir', $attribute->fresh()->name);
        }
    }

    public function test_action_rechecks_unit_state_after_validation_and_does_not_create_partial_data(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $unit = MeasurementUnit::factory()->create();
        MeasurementUnit::whereKey($unit->id)->update(['is_active' => false]);
        try {
            app(SaveCatalogAttribute::class)->handle($actor, null, $this->attributeData(['measurement_unit_id' => $unit->id]));
            $this->fail('La unidad debe comprobarse dentro de la transacción.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('measurement_unit_id', $exception->errors());
            $this->assertDatabaseCount('attributes', 0);
        }
    }

    public function test_action_rejects_raw_decimal_overprecision_before_eloquent_can_round(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $unit = MeasurementUnit::factory()->create(['decimal_places' => 2]);
        $attribute = $this->technicalAttribute(['measurement_unit_id' => $unit->id]);
        foreach (['1.0000001', '1.001000'] as $minimum) {
            try {
                app(SaveCategoryAttribute::class)->handle($actor, $category, null, $this->associationData(['attribute_id' => $attribute->id, 'min_value' => $minimum]));
                $this->fail('No se debe redondear un límite inválido.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('min_value', $exception->errors());
                $this->assertDatabaseCount('category_attributes', 0);
            }
        }
    }
}
