<?php

namespace Tests\Feature\Catalog;

use App\Models\AtributoCatalogo;
use App\Models\Categoria;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class TechnicalAttributeManagementTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public function test_admin_and_manager_create_edit_and_change_state_with_server_authors(): void
    {
        foreach ([1, 2] as $rol) {
            $actor = Usuario::factory()->create(['rol_id' => $rol]);
            $this->signIn($actor)->get(route('atributos.create'))->assertOk()->assertSee('Nuevo atributo técnico');
            $this->post(route('atributos.store'), $this->attributeData(['codigo' => ' attr-'.$rol.' ', 'creado_por' => 999, 'modificado_por' => 999]))->assertRedirect(route('atributos.index'));
            $atributo = AtributoCatalogo::where('codigo', 'ATTR-'.$rol)->firstOrFail();
            $this->assertSame($actor->id, $atributo->creador->id);
            $this->assertSame($actor->id, $atributo->modificador->id);
            $this->get(route('atributos.edit', $atributo))->assertOk();
            $this->put(route('atributos.update', $atributo), $this->attributeData(['codigo' => $atributo->codigo, 'nombre' => 'Nombre corregido']))->assertRedirect();
            $this->patch(route('atributos.estado', $atributo), ['activo' => 0, 'nombre' => 'Alterado'])->assertRedirect();
            $this->assertFalse($atributo->fresh()->activo);
            $this->assertSame('Nombre corregido', $atributo->fresh()->nombre);
            $this->patch(route('atributos.estado', $atributo), ['activo' => 1])->assertRedirect();
            $this->assertTrue($atributo->fresh()->activo);
        }
    }

    public function test_all_four_types_are_accepted_and_only_decimal_may_have_a_unit(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (array_keys(AtributoCatalogo::TYPES) as $tipo) {
            $this->post(route('atributos.store'), $this->attributeData(['codigo' => $tipo, 'tipo_dato' => $tipo, 'unidad_medida_id' => $tipo === 'decimal' ? $unidad->id : null]))->assertRedirect()->assertSessionHasNoErrors();
            $this->assertDatabaseHas('atributos', ['codigo' => strtoupper($tipo), 'tipo_dato' => $tipo]);
            if ($tipo !== 'decimal') {
                $this->post(route('atributos.store'), $this->attributeData(['codigo' => $tipo.'-UNIT', 'tipo_dato' => $tipo, 'unidad_medida_id' => $unidad->id]))->assertSessionHasErrors('unidad_medida_id');
            }
        }
        $this->assertSame(1, $unidad->atributos()->count());
    }

    public function test_required_lengths_type_unit_and_state_are_validated_in_spanish(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['codigo', 'nombre', 'tipo_dato', 'activo'] as $field) {
            $data = $this->attributeData();
            unset($data[$field]);
            $this->post(route('atributos.store'), $data)->assertSessionHasErrors($field);
        }
        foreach ([['codigo' => ' '], ['nombre' => ' '], ['codigo' => str_repeat('x', 51)], ['nombre' => str_repeat('x', 101)], ['texto_ayuda' => str_repeat('x', 256)], ['tipo_dato' => 'Decimal'], ['tipo_dato' => 'integer'], ['unidad_medida_id' => 999999], ['activo' => 2]] as $changes) {
            $this->post(route('atributos.store'), $this->attributeData($changes))->assertSessionHasErrors();
        }
        $this->post(route('atributos.store'), $this->attributeData(['nombre' => '']))->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);
        $this->assertDatabaseCount('atributos', 0);
    }

    public function test_duplicate_codes_are_rejected_even_when_inactive_but_names_may_repeat(): void
    {
        $atributo = $this->technicalAttribute(['codigo' => 'TEMP', 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('atributos.store'), $this->attributeData(['codigo' => ' temp ']))->assertSessionHasErrors('codigo');
        $this->post(route('atributos.store'), $this->attributeData(['codigo' => 'OTRA', 'nombre' => $atributo->nombre]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('atributos', 2);
    }

    public function test_type_and_unit_can_change_before_association_and_creator_is_preserved(): void
    {
        $creador = Usuario::factory()->create(['rol_id' => 1]);
        $editor = Usuario::factory()->create(['rol_id' => 2]);
        $atributo = $this->technicalAttribute(['tipo_dato' => 'text']);
        $atributo->creado_por = $creador->id;
        $atributo->save();
        $unidad = UnidadMedida::factory()->create();
        $this->signIn($editor)->put(route('atributos.update', $atributo), $this->attributeData(['codigo' => $atributo->codigo, 'unidad_medida_id' => $unidad->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('decimal', $atributo->fresh()->tipo_dato);
        $this->assertSame($unidad->id, $atributo->fresh()->unidad_medida_id);
        $this->assertSame($creador->id, $atributo->fresh()->creado_por);
        $this->assertSame($editor->id, $atributo->fresh()->modificado_por);
    }

    public function test_any_association_freezes_type_and_unit_even_if_inactive_and_rollback_preserves_metadata(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $atributo = $this->technicalAttribute(['unidad_medida_id' => $unidad->id]);
        $association = $this->association(Categoria::factory()->create(), $atributo, ['activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach ([['tipo_dato' => 'text', 'unidad_medida_id' => null], ['unidad_medida_id' => null]] as $changes) {
            $this->put(route('atributos.update', $atributo), $this->attributeData(array_replace(['codigo' => $atributo->codigo, 'nombre' => 'No persistir', 'unidad_medida_id' => $unidad->id], $changes)))->assertSessionHasErrors('tipo_dato');
            $this->assertSame('decimal', $atributo->fresh()->tipo_dato);
            $this->assertSame($unidad->id, $atributo->fresh()->unidad_medida_id);
            $this->assertNotSame('No persistir', $atributo->fresh()->nombre);
        }
        $this->put(route('atributos.update', $atributo), $this->attributeData(['codigo' => $atributo->codigo, 'unidad_medida_id' => $unidad->id, 'nombre' => 'Nombre seguro']))->assertSessionHasNoErrors();
        $this->assertSame($atributo->id, $association->fresh()->atributo->id);
    }

    public function test_active_association_blocks_global_deactivation_from_both_endpoints(): void
    {
        $atributo = $this->technicalAttribute();
        $association = $this->association(Categoria::factory()->create(), $atributo);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->patch(route('atributos.estado', $atributo), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->put(route('atributos.update', $atributo), $this->attributeData(['codigo' => $atributo->codigo, 'activo' => 0, 'nombre' => 'No persistir']))->assertSessionHasErrors('activo');
        $this->assertTrue($atributo->fresh()->activo);
        $this->patch(route('atributos-categoria.estado', [$association->categoria, $association]), ['activo' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->patch(route('atributos.estado', $atributo), ['activo' => 0])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($atributo->fresh()->activo);
        $this->assertDatabaseHas('atributos_categoria', ['id' => $association->id, 'atributo_id' => $atributo->id]);
        $this->delete('/atributos-tecnicos/'.$atributo->id)->assertStatus(405);
    }

    public function test_inactive_unit_blocks_new_attributes_and_activation_but_allows_historical_name_correction(): void
    {
        $unidad = UnidadMedida::factory()->create(['activo' => false]);
        $atributo = $this->technicalAttribute(['unidad_medida_id' => $unidad->id, 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('atributos.store'), $this->attributeData(['unidad_medida_id' => $unidad->id]))->assertSessionHasErrors('unidad_medida_id');
        $this->patch(route('atributos.estado', $atributo), ['activo' => 1])->assertSessionHasErrors('unidad_medida_id');
        $this->put(route('atributos.update', $atributo), $this->attributeData(['codigo' => $atributo->codigo, 'unidad_medida_id' => $unidad->id, 'activo' => 0, 'nombre' => 'Corrección histórica']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Corrección histórica', $atributo->fresh()->nombre);
        $this->assertFalse($atributo->fresh()->activo);
    }

    public function test_actual_attribute_references_protect_unit_state_and_semantics(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $atributo = $this->technicalAttribute(['unidad_medida_id' => $unidad->id]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->patch(route('unidades-medida.estado', $unidad), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->patch(route('atributos.estado', $atributo), ['activo' => 0])->assertSessionHasNoErrors();
        $this->patch(route('unidades-medida.estado', $unidad), ['activo' => 0])->assertSessionHasNoErrors();
        foreach (['codigo' => 'OTRO', 'simbolo' => 'XX', 'dimension' => $unidad->dimension === 'other' ? 'length' : 'other', 'decimales' => $unidad->decimales === 6 ? 0 : 6] as $field => $value) {
            $this->put(route('unidades-medida.update', $unidad), array_replace($unidad->fresh()->only(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo']), [$field => $value]))->assertSessionHasErrors('unidad_medida');
        }
        $this->put(route('unidades-medida.update', $unidad), array_replace($unidad->fresh()->only(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo']), ['nombre' => 'Unidad corregida']))->assertSessionHasNoErrors();
    }

    public function test_search_type_state_pagination_wildcards_and_escaped_help(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        for ($i = 0; $i < 22; $i++) {
            $this->technicalAttribute(['nombre' => 'Filtro '.$i, 'tipo_dato' => 'text', 'activo' => false]);
        }
        $this->get(route('atributos.index', ['q' => 'Filtro', 'tipo_dato' => 'text', 'estado' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('tipo_dato=texto', false);
        $this->get(route('atributos.index', ['q' => 'Filtro', 'tipo_dato' => 'date']))->assertSee('No se encontraron atributos.');
        $atributo = $this->technicalAttribute(['codigo' => 'ATTR%_', 'nombre' => '<script>alert(1)</script>', 'texto_ayuda' => '<img src=x onerror=alert(1)>']);
        $this->get(route('atributos.index', ['q' => '%_']))->assertSee($atributo->codigo)->assertSee('&lt;script&gt;', false)->assertDontSee('<img src=x', false)->assertDontSee('Filtro 0');
        $this->get(route('atributos.index', ['tipo_dato' => 'wrong']))->assertSessionHasErrors('tipo_dato');
    }
}
