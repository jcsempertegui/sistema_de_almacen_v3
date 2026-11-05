<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarCategoria;
use App\Actions\Catalogo\GuardarUnidadMedida;
use App\Models\Categoria;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogUniqueConflictTest extends TestCase
{
    public function test_category_database_collisions_are_translated_and_rolled_back(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        foreach (['codigo', 'nombre'] as $field) {
            $data = ['codigo' => 'OTRA', 'nombre' => 'Otra categoría', 'activo' => true];
            $data[$field] = $categoria->$field;
            try {
                app(GuardarCategoria::class)->handle($actor, null, $data);
                $this->fail('La colisión de base debe rechazarse.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($field, $error->errors());
            }
        }
        $this->assertDatabaseCount('categorias', 1);
    }

    public function test_unit_database_collisions_are_translated_and_rolled_back(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $unidad = UnidadMedida::factory()->create();
        foreach (['codigo', 'simbolo'] as $field) {
            $data = ['codigo' => 'OTRA', 'nombre' => 'Otra unidad', 'simbolo' => 'Z', 'dimension' => 'count', 'decimales' => 0, 'activo' => true];
            $data[$field] = $unidad->$field;
            try {
                app(GuardarUnidadMedida::class)->handle($actor, null, $data);
                $this->fail('La colisión de base debe rechazarse.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($field, $error->errors());
            }
        }
        $this->assertDatabaseCount('unidades_medida', 1);
    }
}
