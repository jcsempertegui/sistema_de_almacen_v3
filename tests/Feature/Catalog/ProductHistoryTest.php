<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarProducto;
use App\Models\Categoria;
use App\Models\RevisionValorAtributoProducto;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Services\HistorialAtributosProducto;
use RuntimeException;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductHistoryTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_edit_clear_and_reactivate_preserve_all_revisions_and_one_value_identity(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']));
        $producto = $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => [$definition->id => 'Anterior']]);
        $id = $producto->valoresAtributos()->firstOrFail()->id;
        foreach (['Nuevo', null, 'Restaurado'] as $value) {
            $producto = app(GuardarProducto::class)->handle($actor, $producto->fresh(), $this->productPayload($producto->fresh(), ['values' => [$definition->id => $value]]));
        }
        $this->assertSame(1, $producto->valoresAtributos()->count());
        $this->assertSame($id, $producto->valoresAtributos()->firstOrFail()->id);
        $this->assertSame(['Anterior', 'Nuevo', 'Nuevo', 'Restaurado'], RevisionValorAtributoProducto::orderBy('revision')->pluck('valor_texto')->all());
        $this->assertSame([true, true, false, true], RevisionValorAtributoProducto::orderBy('revision')->get()->pluck('activo')->all());
        $this->assertSame([1, 2, 3, 4], RevisionValorAtributoProducto::orderBy('revision')->pluck('revision')->all());
        $this->assertSame(4, $producto->version_bloqueo);
        app(GuardarProducto::class)->handle($actor, $producto, $this->productPayload($producto, ['values' => [$definition->id => 'Restaurado']]));
        $this->assertDatabaseCount('revisiones_valor_atributo', 4);
    }

    public function test_failure_after_first_value_and_revision_rolls_back_the_entire_product(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $values = [];
        foreach ([1, 2] as $n) {
            $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']));
            $values[$definition->id] = 'Valor '.$n;
        }
        $real = new HistorialAtributosProducto;
        $calls = 0;
        $mock = \Mockery::mock(HistorialAtributosProducto::class);
        $mock->shouldReceive('record')->twice()->andReturnUsing(function ($row, $operator) use ($real, &$calls) {
            if (++$calls === 2) {
                throw new RuntimeException('Fallo simulado de revisión');
            }
            $real->record($row, $operator);
        });
        $this->app->instance(HistorialAtributosProducto::class, $mock);
        try {
            $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => $values]);
            $this->fail('Se esperaba rollback.');
        } catch (RuntimeException $e) {
            $this->assertSame('Fallo simulado de revisión', $e->getMessage());
        }
        foreach (['productos', 'valores_atributo_producto', 'revisiones_valor_atributo', 'ubicaciones_producto'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_historical_configuration_and_product_structure_cannot_be_reinterpreted(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']));
        $producto = $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => [$definition->id => 'Dato']]);
        $this->signIn($actor);
        foreach (['categoria_id' => Categoria::factory()->create()->id, 'unidad_medida_id' => UnidadMedida::factory()->create()->id, 'modo_control' => 'individual', 'prestable' => 1] as $field => $value) {
            $changes = [$field => $value];
            if ($field === 'prestable') {
                $changes['modo_control'] = 'individual';
            }
            $this->put(route('productos.update', $producto), $this->productPayload($producto, $changes))->assertSessionHasErrors('categoria_id');
        }
        $definition->update(['activo' => false]);
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['nombre' => 'Metadatos corregidos']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('valores_atributo_producto', ['valor_texto' => 'Dato', 'activo' => 1]);
        $this->put(route('atributos-categoria.update', [$categoria, $definition]), $this->associationData(['identificacion_tecnica' => 1, 'activo' => 0]))->assertSessionHasErrors('identificacion_tecnica');
    }

    public function test_required_association_reactivation_is_checked_on_both_endpoints(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']), ['obligatorio' => 1, 'activo' => 0]);
        $this->savedProduct($actor, ['categoria_id' => $categoria->id]);
        $this->signIn($actor)->patch(route('atributos-categoria.estado', [$categoria, $definition]), ['activo' => 1])->assertSessionHasErrors('obligatorio');
        $this->put(route('atributos-categoria.update', [$categoria, $definition]), $this->associationData(['obligatorio' => 1]))->assertSessionHasErrors('obligatorio');
        $this->assertFalse($definition->fresh()->activo);
    }

    public function test_existing_category_and_unit_guards_now_detect_real_product_references(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $producto = $this->savedProduct($actor);
        $this->signIn($actor)->patch(route('categorias.estado', $producto->categoria), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->patch(route('unidades-medida.estado', $producto->unidadMedida), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->assertTrue($producto->categoria->fresh()->activo);
        $this->assertTrue($producto->unidadMedida->fresh()->activo);
    }

    public function test_revisions_have_no_update_or_delete_application_path(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']));
        $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => [$definition->id => 'Anterior']]);
        $revision = RevisionValorAtributoProducto::firstOrFail();
        try {
            $revision->update(['valor_texto' => 'Alterado']);
            $this->fail('Debe ser inmutable.');
        } catch (\LogicException) {
            $this->assertDatabaseHas('revisiones_valor_atributo', ['id' => $revision->id, 'valor_texto' => 'Anterior']);
        }
        try {
            $revision->fresh()->delete();
            $this->fail('Debe conservarse.');
        } catch (\LogicException) {
            $this->assertDatabaseCount('revisiones_valor_atributo', 1);
        }
    }
}
