<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\UnidadMedida;
use App\Models\Usuario;
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
            'invalid tipo' => [['tipo_dato' => 'integer']],
            'case-sensitive tipo' => [['tipo_dato' => 'Decimal']],
            'state check' => [['activo' => 2]],
            'unidad foreign key' => [['unidad_medida_id' => 999999]],
            'author foreign key' => [['creado_por' => 999999]],
            'editor foreign key' => [['modificado_por' => 999999]],
        ];
    }

    #[DataProvider('invalidAttributeRows')]
    public function test_mariadb_enforces_attribute_checks_and_foreign_keys(array $changes): void
    {
        $data = $this->attributeData($changes);
        $this->expectException(QueryException::class);
        DB::table('atributos')->insert($data);
    }

    public function test_mariadb_rejects_unit_on_non_decimal_definition(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('atributos')->insert($this->attributeData(['tipo_dato' => 'text', 'unidad_medida_id' => $unidad->id]));
    }

    public static function invalidAssociationRows(): array
    {
        return [
            'categoria foreign key' => [['categoria_id' => 999999]],
            'atributo foreign key' => [['atributo_id' => 999999]],
            'author foreign key' => [['creado_por' => 999999]],
            'editor foreign key' => [['modificado_por' => 999999]],
            'required check' => [['obligatorio' => 2]],
            'identity check' => [['identificacion_tecnica' => 2]],
            'active check' => [['activo' => 2]],
            'bound check' => [['valor_minimo' => '1.000001', 'valor_maximo' => '1.000000']],
            'unsigned order' => [['orden_presentacion' => -1]],
            'order overflow' => [['orden_presentacion' => 65536]],
            'decimal overflow' => [['valor_maximo' => '1000000000000']],
        ];
    }

    #[DataProvider('invalidAssociationRows')]
    public function test_mariadb_enforces_association_checks_types_and_foreign_keys(array $changes): void
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $data = $this->associationData(array_replace(['categoria_id' => $categoria->id, 'atributo_id' => $atributo->id], $changes));
        $this->expectException(QueryException::class);
        DB::table('atributos_categoria')->insert($data);
    }

    public function test_codes_and_pairs_remain_unique_even_for_inactive_rows(): void
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute(['codigo' => 'TEMP', 'activo' => false]);
        $this->association($categoria, $atributo, ['activo' => false]);
        foreach ([fn () => DB::table('atributos')->insert($this->attributeData(['codigo' => 'temp'])), fn () => DB::table('atributos_categoria')->insert($this->associationData(['categoria_id' => $categoria->id, 'atributo_id' => $atributo->id]))] as $operation) {
            try {
                $operation();
                $this->fail('MariaDB debe conservar la unicidad incluso en registros inactivos.');
            } catch (QueryException) {
                $this->assertDatabaseCount('atributos', 1);
                $this->assertDatabaseCount('atributos_categoria', 1);
            }
        }
    }

    public function test_all_referenced_entities_and_authors_are_restricted_against_deletion(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $unidad = UnidadMedida::factory()->create();
        $atributo = $this->technicalAttribute(['unidad_medida_id' => $unidad->id]);
        $atributo->creado_por = $actor->id;
        $atributo->save();
        $categoria = Categoria::factory()->create();
        $association = $this->association($categoria, $atributo);
        foreach ([['unidades_medida', $unidad->id], ['atributos', $atributo->id], ['categorias', $categoria->id], ['usuarios', $actor->id]] as [$table, $id]) {
            try {
                DB::table($table)->where('id', $id)->delete();
                $this->fail('La clave foránea debe impedir eliminar referencias.');
            } catch (QueryException) {
                $this->assertDatabaseHas($table, ['id' => $id]);
                $this->assertDatabaseHas('atributos_categoria', ['id' => $association->id]);
            }
        }
    }

    public function test_schema_is_innodb_decimal_is_exact_and_future_modules_are_absent(): void
    {
        foreach (['atributos', 'atributos_categoria'] as $table) {
            $this->assertSame('InnoDB', DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->value('ENGINE'));
        }
        $column = DB::selectOne("SELECT COLUMN_TYPE AS tipo FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='atributos_categoria' AND COLUMN_NAME='valor_minimo'");
        $this->assertSame('decimal(18,6)', $column->tipo);
        $indexes = DB::select('SHOW INDEX FROM atributos_categoria');
        $composite = array_filter($indexes, fn ($row) => $row->Key_name === 'category_attributes_id_category_id_unique');
        $this->assertSame(['id', 'categoria_id'], array_values(array_map(fn ($row) => $row->Column_name, $composite)));
        foreach (['movements'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertDatabaseCount('atributos', 0);
        $this->assertDatabaseCount('atributos_categoria', 0);
    }
}
