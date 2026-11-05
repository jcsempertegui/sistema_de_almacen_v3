<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\UnidadMedida;
use App\Models\Usuario;
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
            'categoria author FK' => ['categorias', ['creado_por' => 999999]],
            'categoria editor FK' => ['categorias', ['modificado_por' => 999999]],
            'categoria state CHECK' => ['categorias', ['activo' => 2]],
            'unidad author FK' => ['unidades_medida', ['creado_por' => 999999]],
            'unidad precision CHECK' => ['unidades_medida', ['decimales' => 7]],
            'unidad dimension CHECK' => ['unidades_medida', ['dimension' => 'invalid']],
            'unidad state CHECK' => ['unidades_medida', ['activo' => 2]],
        ];
    }

    #[DataProvider('invalidDatabaseRows')]
    public function test_mariadb_rejects_invalid_catalog_rows(string $table, array $changes): void
    {
        $data = $table === 'categorias' ? ['codigo' => 'DB-CAT', 'nombre' => 'Categoría inválida'] : ['codigo' => 'DB-UNIT', 'nombre' => 'Unidad inválida', 'simbolo' => 'u', 'dimension' => 'count'];
        $this->expectException(QueryException::class);
        DB::table($table)->insert(array_replace($data, $changes));
    }

    public function test_mariadb_enforces_unique_category_codes(): void
    {
        Categoria::factory()->create(['codigo' => 'CAT']);
        $this->expectException(QueryException::class);
        DB::table('categorias')->insert(['codigo' => 'cat', 'nombre' => 'Otro nombre']);
    }

    public function test_mariadb_enforces_unique_category_names(): void
    {
        Categoria::factory()->create(['nombre' => 'Categoría']);
        $this->expectException(QueryException::class);
        DB::table('categorias')->insert(['codigo' => 'OTRA', 'nombre' => 'categoría']);
    }

    public function test_mariadb_enforces_unique_unit_codes(): void
    {
        UnidadMedida::factory()->create(['codigo' => 'METRO']);
        $this->expectException(QueryException::class);
        DB::table('unidades_medida')->insert(['codigo' => 'metro', 'nombre' => 'Otro nombre', 'simbolo' => 'X', 'dimension' => 'length']);
    }

    public function test_exact_duplicate_symbols_are_rejected_in_mariadb(): void
    {
        UnidadMedida::factory()->create(['simbolo' => 'L']);
        $this->expectException(QueryException::class);
        DB::table('unidades_medida')->insert(['codigo' => 'OTRA', 'nombre' => 'Otra unidad', 'simbolo' => 'L', 'dimension' => 'volume']);
    }

    public function test_binary_collation_distinguishes_symbols_and_precision_defaults_to_zero(): void
    {
        foreach (['m', 'M'] as $index => $simbolo) {
            DB::table('unidades_medida')->insert(['codigo' => 'CODE-'.$index, 'nombre' => 'Nombre no único permitido', 'simbolo' => $simbolo, 'dimension' => 'length']);
        }
        $this->assertDatabaseCount('unidades_medida', 2);
        $this->assertSame([0, 0], UnidadMedida::orderBy('id')->pluck('decimales')->all());
        $column = DB::selectOne("SELECT COLLATION_NAME AS collation FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='unidades_medida' AND COLUMN_NAME='simbolo'");
        $this->assertSame('utf8mb4_bin', $column->collation);
    }

    public function test_referenced_authors_cannot_be_deleted(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        Categoria::factory()->create(['creado_por' => $actor->id]);
        $this->expectException(QueryException::class);
        DB::table('usuarios')->where('id', $actor->id)->delete();
    }

    public function test_scope_does_not_create_future_tables_and_catalogs_start_empty(): void
    {
        foreach (['movements'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseCount('categorias', 0);
        $this->assertDatabaseCount('unidades_medida', 0);
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->whereIn('TABLE_NAME', ['categorias', 'unidades_medida'])->pluck('ENGINE')->all();
        $this->assertSame(['InnoDB', 'InnoDB'], $engines);
    }
}
