<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveMeasurementUnit;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogUniqueConflictTest extends TestCase
{
    public function test_category_database_collisions_are_translated_and_rolled_back(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        foreach (['code', 'name'] as $field) {
            $data = ['code' => 'OTRA', 'name' => 'Otra categoría', 'is_active' => true];
            $data[$field] = $category->$field;
            try {
                app(SaveCategory::class)->handle($actor, null, $data);
                $this->fail('La colisión de base debe rechazarse.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($field, $error->errors());
            }
        }
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_unit_database_collisions_are_translated_and_rolled_back(): void
    {
        $actor = User::factory()->create(['role_id' => 2]);
        $unit = MeasurementUnit::factory()->create();
        foreach (['code', 'symbol'] as $field) {
            $data = ['code' => 'OTRA', 'name' => 'Otra unidad', 'symbol' => 'Z', 'dimension' => 'count', 'decimal_places' => 0, 'is_active' => true];
            $data[$field] = $unit->$field;
            try {
                app(SaveMeasurementUnit::class)->handle($actor, null, $data);
                $this->fail('La colisión de base debe rechazarse.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($field, $error->errors());
            }
        }
        $this->assertDatabaseCount('measurement_units', 1);
    }
}
