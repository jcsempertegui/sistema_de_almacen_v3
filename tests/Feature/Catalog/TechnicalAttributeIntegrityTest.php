<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class TechnicalAttributeIntegrityTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public static function invalidAttributeRows(): array
    {
        return [
            'invalid type' => [['data_type' => 'integer']],
            'case-sensitive type' => [['data_type' => 'Decimal']],
            'state check' => [['is_active' => 2]],
            'unit foreign key' => [['measurement_unit_id' => 999999]],
            'author foreign key' => [['created_by' => 999999]],
            'editor foreign key' => [['updated_by' => 999999]],
        ];
    }

    #[DataProvider('invalidAttributeRows')]
    public function test_mariadb_enforces_attribute_checks_and_foreign_keys(array $changes): void
    {
        $data = $this->attributeData($changes);
        $this->expectException(QueryException::class);
        DB::table('attributes')->insert($data);
    }

    public function test_mariadb_rejects_unit_on_non_decimal_definition(): void
    {
        $unit = MeasurementUnit::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('attributes')->insert($this->attributeData(['data_type' => 'text', 'measurement_unit_id' => $unit->id]));
    }

    public static function invalidAssociationRows(): array
    {
        return [
            'category foreign key' => [['category_id' => 999999]],
            'attribute foreign key' => [['attribute_id' => 999999]],
            'author foreign key' => [['created_by' => 999999]],
            'editor foreign key' => [['updated_by' => 999999]],
            'required check' => [['is_required' => 2]],
            'identity check' => [['is_identity' => 2]],
            'active check' => [['is_active' => 2]],
            'bound check' => [['min_value' => '1.000001', 'max_value' => '1.000000']],
            'unsigned order' => [['display_order' => -1]],
            'order overflow' => [['display_order' => 65536]],
            'decimal overflow' => [['max_value' => '1000000000000']],
        ];
    }

    #[DataProvider('invalidAssociationRows')]
    public function test_mariadb_enforces_association_checks_types_and_foreign_keys(array $changes): void
    {
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $data = $this->associationData(array_replace(['category_id' => $category->id, 'attribute_id' => $attribute->id], $changes));
        $this->expectException(QueryException::class);
        DB::table('category_attributes')->insert($data);
    }

    public function test_codes_and_pairs_remain_unique_even_for_inactive_rows(): void
    {
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute(['code' => 'TEMP', 'is_active' => false]);
        $this->association($category, $attribute, ['is_active' => false]);
        foreach ([fn () => DB::table('attributes')->insert($this->attributeData(['code' => 'temp'])), fn () => DB::table('category_attributes')->insert($this->associationData(['category_id' => $category->id, 'attribute_id' => $attribute->id]))] as $operation) {
            try {
                $operation();
                $this->fail('MariaDB debe conservar la unicidad incluso en registros inactivos.');
            } catch (QueryException) {
                $this->assertDatabaseCount('attributes', 1);
                $this->assertDatabaseCount('category_attributes', 1);
            }
        }
    }

    public function test_all_referenced_entities_and_authors_are_restricted_against_deletion(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $unit = MeasurementUnit::factory()->create();
        $attribute = $this->technicalAttribute(['measurement_unit_id' => $unit->id]);
        $attribute->created_by = $actor->id;
        $attribute->save();
        $category = Category::factory()->create();
        $association = $this->association($category, $attribute);
        foreach ([['measurement_units', $unit->id], ['attributes', $attribute->id], ['categories', $category->id], ['users', $actor->id]] as [$table, $id]) {
            try {
                DB::table($table)->where('id', $id)->delete();
                $this->fail('La clave foránea debe impedir eliminar referencias.');
            } catch (QueryException) {
                $this->assertDatabaseHas($table, ['id' => $id]);
                $this->assertDatabaseHas('category_attributes', ['id' => $association->id]);
            }
        }
    }

    public function test_schema_is_innodb_decimal_is_exact_and_future_modules_are_absent(): void
    {
        foreach (['attributes', 'category_attributes'] as $table) {
            $this->assertSame('InnoDB', DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->value('ENGINE'));
        }
        $column = DB::selectOne("SELECT COLUMN_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='category_attributes' AND COLUMN_NAME='min_value'");
        $this->assertSame('decimal(18,6)', $column->type);
        $indexes = DB::select('SHOW INDEX FROM category_attributes');
        $composite = array_filter($indexes, fn ($row) => $row->Key_name === 'category_attributes_id_category_id_unique');
        $this->assertSame(['id', 'category_id'], array_values(array_map(fn ($row) => $row->Column_name, $composite)));
        foreach (['movements'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseCount('attributes', 0);
        $this->assertDatabaseCount('category_attributes', 0);
    }
}
