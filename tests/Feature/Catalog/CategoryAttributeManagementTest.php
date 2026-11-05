<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Services\ReferenciasAtributo;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class CategoryAttributeManagementTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public function test_admin_and_manager_associate_edit_and_toggle_without_changing_identity_or_authors(): void
    {
        $atributo = $this->technicalAttribute();
        foreach ([1, 2] as $rol) {
            $actor = Usuario::factory()->create(['rol_id' => $rol]);
            $categoria = Categoria::factory()->create();
            $this->signIn($actor)->get(route('atributos-categoria.create', $categoria))->assertOk();
            $this->post(route('atributos-categoria.store', $categoria), $this->associationData(['atributo_id' => $atributo->id, 'obligatorio' => 1, 'identificacion_tecnica' => 1, 'orden_presentacion' => 65535, 'valor_minimo' => '-10.500000', 'valor_maximo' => '0', 'creado_por' => 999]))->assertRedirect()->assertSessionHasNoErrors();
            $association = $categoria->atributosCategoria()->firstOrFail();
            $this->assertSame($actor->id, $association->creador->id);
            $this->assertSame($atributo->id, $association->atributo->id);
            $this->assertSame('-10.500000', $association->valor_minimo);
            $this->assertSame('0.000000', $association->valor_maximo);
            $this->get(route('atributos-categoria.edit', [$categoria, $association]))->assertOk();
            $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['orden_presentacion' => 3]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(3, $association->fresh()->orden_presentacion);
            $this->patch(route('atributos-categoria.estado', [$categoria, $association]), ['activo' => 0])->assertSessionHasNoErrors();
            $this->assertFalse($association->fresh()->activo);
            $this->patch(route('atributos-categoria.estado', [$categoria, $association]), ['activo' => 1])->assertSessionHasNoErrors();
            $this->assertTrue($association->fresh()->activo);
            $this->assertSame($actor->id, $association->fresh()->modificador->id);
            $this->delete('/categorias/'.$categoria->id.'/atributos/'.$association->id)->assertStatus(405);
        }
    }

    public function test_duplicate_associations_are_rejected_even_when_inactive_and_picker_excludes_them(): void
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute(['nombre' => 'Ya asociado']);
        $this->association($categoria, $atributo, ['activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('atributos-categoria.store', $categoria), $this->associationData(['atributo_id' => $atributo->id]))->assertSessionHasErrors('atributo_id');
        $this->get(route('atributos-categoria.create', $categoria))->assertDontSee('Ya asociado');
        $this->assertDatabaseCount('atributos_categoria', 1);
    }

    public function test_wrong_parent_returns_404_for_edit_update_and_state_and_parent_input_is_prohibited(): void
    {
        $categoria = Categoria::factory()->create();
        $wrong = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $association = $this->association($categoria, $atributo);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->get(route('atributos-categoria.edit', [$wrong, $association]))->assertNotFound();
        $this->put(route('atributos-categoria.update', [$wrong, $association]), $this->associationData())->assertNotFound();
        $this->patch(route('atributos-categoria.estado', [$wrong, $association]), ['activo' => 0])->assertNotFound();
        $this->post(route('atributos-categoria.store', $wrong), $this->associationData(['atributo_id' => $atributo->id, 'categoria_id' => $categoria->id]))->assertSessionHasErrors('categoria_id');
        $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['atributo_id' => $atributo->id]))->assertSessionHasErrors('atributo_id');
        $this->assertSame($categoria->id, $association->fresh()->categoria_id);
        $this->assertTrue($association->fresh()->activo);
    }

    public function test_required_flags_order_and_attribute_references_are_validated(): void
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $data = $this->associationData(['atributo_id' => $atributo->id]);
        foreach (['atributo_id', 'obligatorio', 'identificacion_tecnica', 'orden_presentacion', 'activo'] as $field) {
            $missing = $data;
            unset($missing[$field]);
            $this->post(route('atributos-categoria.store', $categoria), $missing)->assertSessionHasErrors($field);
        }
        foreach ([['atributo_id' => 999999], ['obligatorio' => 2], ['identificacion_tecnica' => 2], ['activo' => 2], ['orden_presentacion' => -1], ['orden_presentacion' => 65536], ['orden_presentacion' => '1.5']] as $changes) {
            $this->post(route('atributos-categoria.store', $categoria), array_replace($data, $changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('atributos_categoria', 0);
    }

    public function test_decimal_format_range_and_order_are_exact_and_do_not_round_invalid_values(): void
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $data = $this->associationData(['atributo_id' => $atributo->id]);
        foreach (['1e3', '1,5', '1.0000001', '1000000000000', 'NaN', '1 000', ['1'], 0.5] as $value) {
            $this->postJson(route('atributos-categoria.store', $categoria), array_replace($data, ['valor_minimo' => $value]))->assertUnprocessable()->assertJsonValidationErrors('valor_minimo');
        }
        foreach ([['valor_minimo' => '0.000002', 'valor_maximo' => '0.000001'], ['valor_minimo' => '-0.000001', 'valor_maximo' => '-0.000002'], ['valor_minimo' => '999999999999.999999', 'valor_maximo' => '999999999999.999998']] as $changes) {
            $this->postJson(route('atributos-categoria.store', $categoria), array_replace($data, $changes))->assertUnprocessable()->assertJsonValidationErrors('valor_maximo');
        }
        $this->post(route('atributos-categoria.store', $categoria), array_replace($data, ['valor_minimo' => '-999999999999.999999', 'valor_maximo' => '999999999999.999999']))->assertSessionHasNoErrors();
        $association = $categoria->atributosCategoria()->firstOrFail();
        $this->assertSame('-999999999999.999999', $association->valor_minimo);
        $this->assertSame('999999999999.999999', $association->valor_maximo);
    }

    public function test_unit_precision_zero_accepts_padded_integers_but_rejects_significant_fraction(): void
    {
        $categoria = Categoria::factory()->create();
        $unidad = UnidadMedida::factory()->create(['decimales' => 0]);
        $atributo = $this->technicalAttribute(['unidad_medida_id' => $unidad->id]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $data = $this->associationData(['atributo_id' => $atributo->id, 'valor_minimo' => '1.000000', 'valor_maximo' => '2.000000']);
        $this->post(route('atributos-categoria.store', $categoria), array_replace($data, ['valor_minimo' => '1.1']))->assertSessionHasErrors('valor_minimo');
        $this->post(route('atributos-categoria.store', $categoria), $data)->assertSessionHasNoErrors();
        $association = $categoria->atributosCategoria()->firstOrFail();
        $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['valor_minimo' => $association->valor_minimo, 'valor_maximo' => $association->valor_maximo, 'orden_presentacion' => 1]))->assertSessionHasNoErrors();
        $this->assertSame('1.000000', $association->fresh()->valor_minimo);
    }

    public function test_non_decimal_types_reject_any_bound_including_zero(): void
    {
        $categoria = Categoria::factory()->create();
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['text', 'boolean', 'date'] as $tipo) {
            $atributo = $this->technicalAttribute(['tipo_dato' => $tipo]);
            foreach ([['valor_minimo' => '0'], ['valor_maximo' => '0']] as $changes) {
                $this->post(route('atributos-categoria.store', $categoria), $this->associationData(array_replace(['atributo_id' => $atributo->id], $changes)))->assertSessionHasErrors('valor_minimo');
            }
        }
        $this->assertDatabaseCount('atributos_categoria', 0);
    }

    public function test_inactive_category_attribute_or_unit_prevents_new_or_reactivated_associations(): void
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $association = $this->association($categoria, $atributo, ['activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach ([$categoria, $atributo] as $reference) {
            $reference->activo = false;
            $reference->save();
            $this->patch(route('atributos-categoria.estado', [$categoria, $association]), ['activo' => 1])->assertSessionHasErrors('atributo_id');
            $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['activo' => 1]))->assertSessionHasErrors('atributo_id');
            $otherCategory = $reference === $categoria ? $categoria : Categoria::factory()->create();
            $otherAttribute = $reference === $categoria ? $this->technicalAttribute() : $atributo;
            $this->post(route('atributos-categoria.store', $otherCategory), $this->associationData(['atributo_id' => $otherAttribute->id]))->assertSessionHasErrors('atributo_id');
            $reference->activo = true;
            $reference->save();
        }
        $unidad = UnidadMedida::factory()->create(['activo' => false]);
        $atributo->unidad_medida_id = $unidad->id;
        $atributo->save();
        $this->patch(route('atributos-categoria.estado', [$categoria, $association]), ['activo' => 1])->assertSessionHasErrors('atributo_id');
        $this->assertFalse($association->fresh()->activo);
    }

    public function test_historical_impact_guards_with_simulated_future_dependencies_rollback_all_changes(): void
    {
        $categoria = Categoria::factory()->create();
        $association = $this->association($categoria, $this->technicalAttribute(), ['valor_minimo' => '-1']);
        $references = $this->partialMock(ReferenciasAtributo::class);
        $references->shouldReceive('associationHasValues')->twice()->with($association->id)->andReturn(true);
        $references->shouldReceive('categoryHasIncompleteProducts')->once()->andReturn(true);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['identificacion_tecnica' => 1, 'valor_minimo' => '-1', 'orden_presentacion' => 7]))->assertSessionHasErrors('identificacion_tecnica');
        $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['valor_minimo' => '-2']))->assertSessionHasErrors('identificacion_tecnica');
        $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData(['valor_minimo' => '-1', 'obligatorio' => 1]))->assertSessionHasErrors('obligatorio');
        $this->assertFalse($association->fresh()->identificacion_tecnica);
        $this->assertFalse($association->fresh()->obligatorio);
        $this->assertSame(0, $association->fresh()->orden_presentacion);
        $this->assertSame('-1.000000', $association->fresh()->valor_minimo);
    }

    public function test_list_is_scoped_ordered_filtered_and_preserves_inactive_configuration(): void
    {
        $categoria = Categoria::factory()->create();
        $other = Categoria::factory()->create();
        $first = $this->association($categoria, $this->technicalAttribute(['nombre' => 'Primero']), ['orden_presentacion' => 1]);
        $this->association($categoria, $this->technicalAttribute(['nombre' => 'Segundo']), ['orden_presentacion' => 2, 'activo' => false]);
        $this->association($other, $this->technicalAttribute(['nombre' => 'Ajeno']));
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        $this->get(route('atributos-categoria.index', $categoria))->assertOk()->assertSeeInOrder(['Primero', 'Segundo'])->assertDontSee('Ajeno')->assertDontSee('Editar');
        $this->get(route('atributos-categoria.index', [$categoria, 'estado' => 'inactive']))->assertSee('Segundo')->assertDontSee('Primero');
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]))->patch(route('categorias.estado', $categoria), ['activo' => 0])->assertSessionHasNoErrors();
        $this->assertTrue($first->fresh()->activo);
        $this->get(route('atributos-categoria.index', $categoria))->assertOk()->assertSee('Primero');
        $this->patch(route('atributos-categoria.estado', [$categoria, $first]), ['activo' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('atributos_categoria', ['id' => $first->id, 'categoria_id' => $categoria->id]);
    }
}
