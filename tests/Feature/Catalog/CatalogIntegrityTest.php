<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogIntegrityTest extends TestCase
{
    public static function invalidDatabaseRows(): array
    {
        return [
            'category author FK' => ['categories', ['created_by' => 999999]],
            'category editor FK' => ['categories', ['updated_by' => 999999]],
            'category state CHECK' => ['categories', ['is_active' => 2]],
            'unit author FK' => ['measurement_units', ['created_by' => 999999]],
            'unit precision CHECK' => ['measurement_units', ['decimal_places' => 7]],
            'unit dimension CHECK' => ['measurement_units', ['dimension' => 'invalid']],
            'unit state CHECK' => ['measurement_units', ['is_active' => 2]],
        ];
    }

    #[DataProvider('invalidDatabaseRows')]
    public function test_mariadb_rejects_invalid_catalog_rows(string $table, array $changes): void
    {
        $data = $table === 'categories' ? ['code' => 'DB-CAT', 'name' => 'Categoría inválida'] : ['code' => 'DB-UNIT', 'name' => 'Unidad inválida', 'symbol' => 'u', 'dimension' => 'count'];
        $this->expectException(QueryException::class);
        DB::table($table)->insert(array_replace($data, $changes));
    }

    public function test_mariadb_enforces_unique_category_codes(): void
    {
        Category::factory()->create(['code' => 'CAT']);
        $this->expectException(QueryException::class);
        DB::table('categories')->insert(['code' => 'cat', 'name' => 'Otro nombre']);
    }

    public function test_mariadb_enforces_unique_category_names(): void
    {
        Category::factory()->create(['name' => 'Categoría']);
        $this->expectException(QueryException::class);
        DB::table('categories')->insert(['code' => 'OTRA', 'name' => 'categoría']);
    }

    public function test_mariadb_enforces_unique_unit_codes(): void
    {
        MeasurementUnit::factory()->create(['code' => 'METRO']);
        $this->expectException(QueryException::class);
        DB::table('measurement_units')->insert(['code' => 'metro', 'name' => 'Otro nombre', 'symbol' => 'X', 'dimension' => 'length']);
    }

    public function test_exact_duplicate_symbols_are_rejected_in_mariadb(): void
    {
        MeasurementUnit::factory()->create(['symbol' => 'L']);
        $this->expectException(QueryException::class);
        DB::table('measurement_units')->insert(['code' => 'OTRA', 'name' => 'Otra unidad', 'symbol' => 'L', 'dimension' => 'volume']);
    }

    public function test_binary_collation_distinguishes_symbols_and_precision_defaults_to_zero(): void
    {
        foreach (['m', 'M'] as $index => $symbol) {
            DB::table('measurement_units')->insert(['code' => 'CODE-'.$index, 'name' => 'Nombre no único permitido', 'symbol' => $symbol, 'dimension' => 'length']);
        }
        $this->assertDatabaseCount('measurement_units', 2);
        $this->assertSame([0, 0], MeasurementUnit::orderBy('id')->pluck('decimal_places')->all());
        $column = DB::selectOne("SELECT COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='measurement_units' AND COLUMN_NAME='symbol'");
        $this->assertSame('utf8mb4_bin', $column->collation);
    }

    public function test_referenced_authors_cannot_be_deleted(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        Category::factory()->create(['created_by' => $actor->id]);
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $actor->id)->delete();
    }

    public function test_scope_does_not_create_future_tables_and_catalogs_start_empty(): void
    {
        foreach (['products', 'attributes', 'category_attributes', 'locations', 'individual_units', 'product_locations'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('measurement_units', 0);
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', ['categories', 'measurement_units'])->pluck('ENGINE')->all();
        $this->assertSame(['InnoDB', 'InnoDB'], $engines);
    }
}
