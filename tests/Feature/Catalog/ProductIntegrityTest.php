<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductLocation;
use App\Models\User;
use App\Services\ProductAttributeHistory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductIntegrityTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_revision_constraints_and_parent_restriction_preserve_snapshots(): void
    {
        $value = ProductAttributeValue::factory()->create();
        $actor = User::factory()->create(['role_id' => 1]);
        app(ProductAttributeHistory::class)->record($value, $actor);
        $data = ['product_attribute_value_id' => $value->id, 'revision' => 2, 'value_text' => 'Dato', 'is_active' => 1, 'created_by' => $actor->id, 'created_at' => now()];
        foreach ([['revision' => 1], ['revision' => 0], ['value_text' => null], ['value_boolean' => 0], ['is_active' => 2], ['created_by' => 999999], ['product_attribute_value_id' => 999999]] as $changes) {
            try {
                DB::table('product_attribute_value_revisions')->insert(array_replace($data, $changes));
                $this->fail('La revisión debe conservar integridad.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
        try {
            DB::table('product_attribute_values')->where('id', $value->id)->delete();
            $this->fail('Debe conservar la fila histórica.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
        $this->assertDatabaseCount('product_attribute_value_revisions', 1);
    }

    public static function invalidProducts(): array
    {
        return ['mode' => [['control_mode' => 'other']], 'case' => [['control_mode' => 'Quantity']], 'loan' => [['is_loanable' => 1]], 'active' => [['is_active' => 2]], 'loan flag' => [['is_loanable' => 2]], 'version' => [['lock_version' => 0]], 'category' => [['category_id' => 999999]], 'unit' => [['measurement_unit_id' => 999999]], 'author' => [['created_by' => 999999]]];
    }

    #[DataProvider('invalidProducts')]
    public function test_mariadb_rejects_invalid_products(array $changes): void
    {
        $data = $this->productData();
        unset($data['values']);
        $this->expectException(QueryException::class);
        DB::table('products')->insert(array_replace($data, $changes));
    }

    public static function invalidValues(): array
    {
        return ['none' => [['value_text' => null]], 'two' => [['value_boolean' => 0]], 'boolean' => [['value_text' => null, 'value_boolean' => 2]], 'state' => [['is_active' => 2]], 'precision overflow' => [['value_text' => null, 'value_decimal' => '1000000000000']], 'missing product' => [['product_id' => 999999]], 'missing association' => [['category_attribute_id' => 999999]]];
    }

    #[DataProvider('invalidValues')]
    public function test_mariadb_rejects_invalid_values(array $changes): void
    {
        $fixture = ProductAttributeValue::factory()->create();
        $data = array_intersect_key($fixture->getAttributes(), array_flip(['product_id', 'category_id', 'category_attribute_id', 'value_text', 'value_decimal', 'value_boolean', 'value_date', 'is_active']));
        // Change the unique pair first so this verifies CHECK/FK, not duplicate rejection.
        $product = Product::factory()->create(['category_id' => $fixture->category_id]);
        $data['product_id'] = $product->id;
        $this->expectException(QueryException::class);
        DB::table('product_attribute_values')->insert(array_replace($data, $changes));
    }

    public function test_both_composite_keys_reject_cross_category_values_even_with_valid_individual_ids(): void
    {
        $fixture = ProductAttributeValue::factory()->create();
        $other = Product::factory()->create();
        foreach ([$fixture->category_id, $other->category_id] as $categoryId) {
            try {
                DB::table('product_attribute_values')->insert(['product_id' => $other->id, 'category_id' => $categoryId, 'category_attribute_id' => $fixture->category_attribute_id, 'value_text' => 'Inválido']);
                $this->fail('La pertenencia debe estar protegida por FK compuesta.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
        $this->assertDatabaseCount('product_attribute_values', 1);
    }

    public function test_unique_codes_values_links_and_restrict_rules_preserve_inactive_history(): void
    {
        $fixture = ProductAttributeValue::factory()->create(['is_active' => false]);
        $product = $fixture->product;
        $link = ProductLocation::factory()->create(['product_id' => $product->id, 'is_active' => false]);
        $duplicate = $this->productData(['internal_code' => strtolower($product->internal_code)]);
        unset($duplicate['values']);
        $operations = [
            fn () => DB::table('products')->insert($duplicate),
            fn () => DB::table('product_attribute_values')->insert(['product_id' => $product->id, 'category_id' => $product->category_id, 'category_attribute_id' => $fixture->category_attribute_id, 'value_text' => 'Duplicado']),
            fn () => DB::table('product_locations')->insert(['product_id' => $product->id, 'location_id' => $link->location_id]),
            fn () => DB::table('products')->where('id', $product->id)->delete(),
            fn () => DB::table('locations')->where('id', $link->location_id)->delete(),
            fn () => DB::table('category_attributes')->where('id', $fixture->category_attribute_id)->delete(),
            fn () => DB::table('products')->where('id', $product->id)->update(['category_id' => Category::factory()->create()->id]),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Se esperaba una restricción de integridad.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
    }

    public function test_schema_types_engines_foreign_keys_and_excluded_scope(): void
    {
        foreach (['products', 'product_attribute_values', 'product_attribute_value_revisions', 'product_locations'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame('InnoDB', DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', 'sga_rio_grande_testing')->where('TABLE_NAME', $table)->value('ENGINE'));
            $constraints = DB::select('SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            $this->assertNotEmpty($constraints);
            foreach ($constraints as $constraint) {
                $this->assertSame('RESTRICT', $constraint->UPDATE_RULE);
                $this->assertSame('RESTRICT', $constraint->DELETE_RULE);
            }
        }
        $column = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', 'sga_rio_grande_testing')->where('TABLE_NAME', 'product_attribute_values')->where('COLUMN_NAME', 'value_decimal')->first();
        $this->assertSame('decimal', $column->DATA_TYPE);
        $this->assertSame(18, (int) $column->NUMERIC_PRECISION);
        $this->assertSame(6, (int) $column->NUMERIC_SCALE);
        foreach (['movements', 'stocks', 'loans'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('products', 'quantity'));
    }
}
