<?php

namespace Tests\Feature\Catalog;

use App\Models\Ubicacion;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocationIntegrityTest extends TestCase
{
    public static function invalidRows(): array
    {
        return [
            'tipo' => [['tipo' => 'aisle']],
            'tipo case' => [['tipo' => 'Sector']],
            'active flag' => [['activo' => 2]],
            'storable flag' => [['permite_almacenar' => 2]],
            'padre FK' => [['padre_id' => 999999]],
            'author FK' => [['creado_por' => 999999]],
            'editor FK' => [['modificado_por' => 999999]],
        ];
    }

    #[DataProvider('invalidRows')]
    public function test_mariadb_rejects_invalid_rows(array $changes): void
    {
        $this->expectException(QueryException::class);
        DB::table('ubicaciones')->insert(array_replace(['codigo' => 'DB-LOC', 'nombre' => 'Ubicación', 'tipo' => 'sector'], $changes));
    }

    public function test_unique_code_is_case_insensitive_even_for_inactive_rows(): void
    {
        Ubicacion::factory()->create(['codigo' => 'LOC-A', 'activo' => false]);
        $this->expectException(QueryException::class);
        DB::table('ubicaciones')->insert(['codigo' => 'loc-a', 'nombre' => 'Otro nombre', 'tipo' => 'sector']);
    }

    public function test_parent_and_authors_are_restricted_against_deletion_and_key_updates(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $editor = Usuario::factory()->create(['rol_id' => 2]);
        $root = Ubicacion::factory()->create(['creado_por' => $actor->id, 'modificado_por' => $editor->id]);
        $child = Ubicacion::factory()->create(['padre_id' => $root->id, 'activo' => false]);
        foreach ([fn () => DB::table('ubicaciones')->where('id', $root->id)->delete(), fn () => DB::table('ubicaciones')->where('id', $root->id)->update(['id' => 999999]), fn () => DB::table('usuarios')->where('id', $actor->id)->delete(), fn () => DB::table('usuarios')->where('id', $editor->id)->delete()] as $operation) {
            try {
                $operation();
                $this->fail('La FK debe conservar sus referencias.');
            } catch (QueryException) {
                $this->assertDatabaseHas('ubicaciones', ['id' => $root->id]);
                $this->assertDatabaseHas('ubicaciones', ['id' => $child->id, 'padre_id' => $root->id]);
            }
        }
    }

    public function test_schema_defaults_and_scope_are_correct(): void
    {
        DB::table('ubicaciones')->insert(['codigo' => 'DEFAULT', 'nombre' => 'Valores predeterminados', 'tipo' => 'sector']);
        $node = Ubicacion::where('codigo', 'DEFAULT')->firstOrFail();
        $this->assertTrue($node->activo);
        $this->assertTrue($node->permite_almacenar);
        $this->assertNull($node->padre_id);
        $fk = DB::select("SELECT UPDATE_RULE,DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='ubicaciones'");
        $this->assertCount(3, $fk);
        foreach ($fk as $constraint) {
            $this->assertSame('RESTRICT', $constraint->UPDATE_RULE);
            $this->assertSame('RESTRICT', $constraint->DELETE_RULE);
        }
        $this->assertSame('InnoDB', DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', 'ubicaciones')->value('ENGINE'));
        foreach (['movements'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }
}
