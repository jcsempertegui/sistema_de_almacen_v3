<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UbicacionProducto;
use App\Models\Usuario;
use App\Models\ValorAtributoProducto;
use App\Services\HistorialAtributosProducto;
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
        $value = ValorAtributoProducto::factory()->create();
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        app(HistorialAtributosProducto::class)->record($value, $actor);
        $data = ['valor_atributo_producto_id' => $value->id, 'revision' => 2, 'valor_texto' => 'Dato', 'activo' => 1, 'creado_por' => $actor->id, 'fecha_creacion' => now()];
        foreach ([['revision' => 1], ['revision' => 0], ['valor_texto' => null], ['valor_booleano' => 0], ['activo' => 2], ['creado_por' => 999999], ['valor_atributo_producto_id' => 999999]] as $changes) {
            try {
                DB::table('revisiones_valor_atributo')->insert(array_replace($data, $changes));
                $this->fail('La revisión debe conservar integridad.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
        try {
            DB::table('valores_atributo_producto')->where('id', $value->id)->delete();
            $this->fail('Debe conservar la fila histórica.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->getCode());
        }
        $this->assertDatabaseCount('revisiones_valor_atributo', 1);
    }

    public static function invalidProducts(): array
    {
        return ['mode' => [['modo_control' => 'other']], 'case' => [['modo_control' => 'Quantity']], 'prestamo' => [['prestable' => 1]], 'active' => [['activo' => 2]], 'prestamo flag' => [['prestable' => 2]], 'version' => [['version_bloqueo' => 0]], 'categoria' => [['categoria_id' => 999999]], 'unidad' => [['unidad_medida_id' => 999999]], 'author' => [['creado_por' => 999999]]];
    }

    #[DataProvider('invalidProducts')]
    public function test_mariadb_rejects_invalid_products(array $changes): void
    {
        $data = $this->productData();
        unset($data['values']);
        $this->expectException(QueryException::class);
        DB::table('productos')->insert(array_replace($data, $changes));
    }

    public static function invalidValues(): array
    {
        return ['none' => [['valor_texto' => null]], 'two' => [['valor_booleano' => 0]], 'boolean' => [['valor_texto' => null, 'valor_booleano' => 2]], 'state' => [['activo' => 2]], 'precision overflow' => [['valor_texto' => null, 'valor_decimal' => '1000000000000']], 'missing producto' => [['producto_id' => 999999]], 'missing association' => [['atributo_categoria_id' => 999999]]];
    }

    #[DataProvider('invalidValues')]
    public function test_mariadb_rejects_invalid_values(array $changes): void
    {
        $fixture = ValorAtributoProducto::factory()->create();
        $data = array_intersect_key($fixture->getAttributes(), array_flip(['producto_id', 'categoria_id', 'atributo_categoria_id', 'valor_texto', 'valor_decimal', 'valor_booleano', 'valor_fecha', 'activo']));
        // Change the unique pair first so this verifies CHECK/FK, not duplicate rejection.
        $producto = Producto::factory()->create(['categoria_id' => $fixture->categoria_id]);
        $data['producto_id'] = $producto->id;
        $this->expectException(QueryException::class);
        DB::table('valores_atributo_producto')->insert(array_replace($data, $changes));
    }

    public function test_both_composite_keys_reject_cross_category_values_even_with_valid_individual_ids(): void
    {
        $fixture = ValorAtributoProducto::factory()->create();
        $other = Producto::factory()->create();
        foreach ([$fixture->categoria_id, $other->categoria_id] as $categoryId) {
            try {
                DB::table('valores_atributo_producto')->insert(['producto_id' => $other->id, 'categoria_id' => $categoryId, 'atributo_categoria_id' => $fixture->atributo_categoria_id, 'valor_texto' => 'Inválido']);
                $this->fail('La pertenencia debe estar protegida por FK compuesta.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->getCode());
            }
        }
        $this->assertDatabaseCount('valores_atributo_producto', 1);
    }

    public function test_unique_codes_values_links_and_restrict_rules_preserve_inactive_history(): void
    {
        $fixture = ValorAtributoProducto::factory()->create(['activo' => false]);
        $producto = $fixture->producto;
        $link = UbicacionProducto::factory()->create(['producto_id' => $producto->id, 'activo' => false]);
        $duplicate = $this->productData(['codigo_interno' => strtolower($producto->codigo_interno)]);
        unset($duplicate['values']);
        $operations = [
            fn () => DB::table('productos')->insert($duplicate),
            fn () => DB::table('valores_atributo_producto')->insert(['producto_id' => $producto->id, 'categoria_id' => $producto->categoria_id, 'atributo_categoria_id' => $fixture->atributo_categoria_id, 'valor_texto' => 'Duplicado']),
            fn () => DB::table('ubicaciones_producto')->insert(['producto_id' => $producto->id, 'ubicacion_id' => $link->ubicacion_id]),
            fn () => DB::table('productos')->where('id', $producto->id)->delete(),
            fn () => DB::table('ubicaciones')->where('id', $link->ubicacion_id)->delete(),
            fn () => DB::table('atributos_categoria')->where('id', $fixture->atributo_categoria_id)->delete(),
            fn () => DB::table('productos')->where('id', $producto->id)->update(['categoria_id' => Categoria::factory()->create()->id]),
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
        foreach (['productos', 'valores_atributo_producto', 'revisiones_valor_atributo', 'ubicaciones_producto'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame('InnoDB', DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', 'sga_rio_grande_testing')->where('TABLE_NAME', $table)->value('ENGINE'));
            $constraints = DB::select('SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table]);
            $this->assertNotEmpty($constraints);
            foreach ($constraints as $constraint) {
                $this->assertSame('RESTRICT', $constraint->UPDATE_RULE);
                $this->assertSame('RESTRICT', $constraint->DELETE_RULE);
            }
        }
        $column = DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', 'sga_rio_grande_testing')->where('TABLE_NAME', 'valores_atributo_producto')->where('COLUMN_NAME', 'valor_decimal')->first();
        $this->assertSame('decimal', $column->DATA_TYPE);
        $this->assertSame(18, (int) $column->NUMERIC_PRECISION);
        $this->assertSame(6, (int) $column->NUMERIC_SCALE);
        foreach (['movements', 'stocks', 'loans'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
        $this->assertFalse(Schema::hasColumn('productos', 'cantidad'));
    }
}
