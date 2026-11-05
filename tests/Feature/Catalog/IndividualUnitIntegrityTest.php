<?php

namespace Tests\Feature\Catalog;

use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\TestCase;

class IndividualUnitIntegrityTest extends TestCase
{
    use CreatesIndividualUnitCatalog;

    public static function invalidRows(): array
    {
        return ['blank id' => [['identificador' => ' ']], 'state' => [['activo' => 2]], 'version' => [['version_bloqueo' => 0]], 'blank series' => [['numero_serie' => ' ']], 'producto' => [['producto_id' => 999999]], 'ubicacion' => [['ubicacion_id' => 999999]], 'author' => [['creado_por' => 999999]]];
    }

    #[DataProvider('invalidRows')]
    public function test_sql_constraints_reject_invalid_rows(array $changes): void
    {
        $this->expectException(QueryException::class);
        DB::table('unidades_individuales')->insert(array_replace($this->individualData(), $changes));
    }

    public function test_composite_fk_rejects_cross_product_association_on_insert_and_update(): void
    {
        $producto = Producto::factory()->create(['modo_control' => 'individual']);
        $ubicacion = Ubicacion::factory()->create();
        UbicacionProducto::factory()->create(['ubicacion_id' => $ubicacion->id]);
        $data = $this->individualData(['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id]);
        try {
            DB::table('unidades_individuales')->insert($data);
            $this->fail('Debe rechazar una pareja que no existe.');
        } catch (QueryException $e) {
            $this->assertSame(1452, $e->errorInfo[1]);
        }
        $unidad = UnidadIndividual::factory()->create(['producto_id' => $producto->id]);
        $this->expectException(QueryException::class);
        $unidad->update(['ubicacion_id' => $ubicacion->id]);
    }

    public function test_sql_unique_keys_and_restrict_preserve_identity_and_assigned_pair(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $producto = Producto::factory()->create(['modo_control' => 'individual']);
        $ubicacion = Ubicacion::factory()->create();
        $link = UbicacionProducto::factory()->create(['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id]);
        $unidad = $this->savedIndividual($actor, ['producto_id' => $producto->id, 'ubicacion_id' => $ubicacion->id, 'numero_serie' => 'SN', 'activo' => 0]);
        foreach (['identificador', 'numero_serie'] as $key) {
            $data = $this->individualData(['producto_id' => $producto->id, $key => $unidad->$key]);
            try {
                DB::table('unidades_individuales')->insert($data);
                $this->fail('Debe respetar la unicidad.');
            } catch (QueryException $e) {
                $this->assertSame(1062, $e->errorInfo[1]);
            }
        }
        foreach ([$link, $producto, $ubicacion, $actor] as $padre) {
            try {
                $padre->delete();
                $this->fail('Debe conservar las referencias.');
            } catch (QueryException $e) {
                $this->assertSame(1451, $e->errorInfo[1]);
            }
        }
        $foreignKeys = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='unidades_individuales' AND DELETE_RULE='RESTRICT' AND UPDATE_RULE='RESTRICT'");
        $this->assertCount(5, $foreignKeys);
        $this->assertSame(1, DB::table('unidades_individuales')->count());
    }
}
