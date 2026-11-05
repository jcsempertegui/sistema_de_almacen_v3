<?php

namespace Tests\Feature\Catalog;

use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Services\ReferenciasCatalogo;
use Tests\TestCase;

class MeasurementUnitManagementTest extends TestCase
{
    private function detalle(array $changes = []): array
    {
        return array_replace(['codigo' => 'METRO', 'nombre' => 'Metro', 'simbolo' => 'm', 'dimension' => 'length', 'decimales' => 3, 'activo' => 1], $changes);
    }

    public function test_initial_examples_can_be_registered_by_administrator_and_manager(): void
    {
        $examples = [['UNIDAD', 'Unidad', 'unid.', 'count', 0], ['METRO', 'Metro', 'm', 'length', 3], ['KILO', 'Kilogramo', 'kg', 'mass', 6], ['LITRO', 'Litro', 'L', 'volume', 3]];
        foreach ($examples as $i => [$codigo, $nombre, $simbolo, $dimension, $precision]) {
            $actor = Usuario::factory()->create(['rol_id' => $i % 2 + 1]);
            $this->signIn($actor)->get(route('unidades-medida.create'))->assertOk()->assertSee('Precisión decimal');
            $this->post(route('unidades-medida.store'), $this->detalle(['codigo' => ' '.strtolower($codigo).' ', 'nombre' => $nombre, 'simbolo' => $simbolo, 'dimension' => $dimension, 'decimales' => $precision, 'creado_por' => 999]))->assertRedirect(route('unidades-medida.index'));
            $unidad = UnidadMedida::where('codigo', $codigo)->firstOrFail();
            $this->assertSame($simbolo, $unidad->simbolo);
            $this->assertSame($precision, $unidad->decimales);
            $this->assertSame($actor->id, $unidad->creador->id);
        }
        $this->assertDatabaseCount('unidades_medida', 4);
    }

    public function test_required_fields_precision_dimensions_and_lengths_are_validated(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 2]));
        foreach (['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo'] as $field) {
            $data = $this->detalle();
            unset($data[$field]);
            $this->post(route('unidades-medida.store'), $data)->assertSessionHasErrors($field);
        }
        foreach ([-1, 7, '1.5', 'abc'] as $precision) {
            $this->post(route('unidades-medida.store'), $this->detalle(['decimales' => $precision]))->assertSessionHasErrors('decimales');
        }
        foreach ([['dimension' => 'energy'], ['dimension' => ['length']], ['simbolo' => '   '], ['codigo' => str_repeat('x', 31)], ['nombre' => str_repeat('x', 101)], ['simbolo' => str_repeat('x', 21)], ['activo' => 2]] as $changes) {
            $this->post(route('unidades-medida.store'), $this->detalle($changes))->assertSessionHasErrors();
        }
        $this->post(route('unidades-medida.store'), $this->detalle(['decimales' => 7]))->assertSessionHasErrors(['decimales' => 'La precisión decimal debe estar entre 0 y 6.']);
        $this->assertDatabaseCount('unidades_medida', 0);
    }

    public function test_all_six_dimensions_are_accepted_without_conversions(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 2]));
        foreach (array_keys(UnidadMedida::DIMENSIONS) as $dimension) {
            $this->post(route('unidades-medida.store'), $this->detalle(['codigo' => $dimension, 'simbolo' => $dimension, 'dimension' => $dimension]))->assertRedirect();
        }
        $this->assertDatabaseCount('unidades_medida', 6);
    }

    public function test_symbols_are_case_sensitive_trimmed_and_exact_duplicates_are_rejected(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('unidades-medida.store'), $this->detalle(['simbolo' => ' m ']))->assertRedirect();
        $this->post(route('unidades-medida.store'), $this->detalle(['codigo' => 'DISTINTA', 'simbolo' => 'M']))->assertRedirect();
        $this->post(route('unidades-medida.store'), $this->detalle(['codigo' => 'OTRA', 'simbolo' => 'm']))->assertSessionHasErrors('simbolo');
        $this->post(route('unidades-medida.store'), $this->detalle(['codigo' => ' metro ', 'simbolo' => 'x']))->assertSessionHasErrors('codigo');
        $this->assertDatabaseCount('unidades_medida', 2);
        $this->assertDatabaseHas('unidades_medida', ['codigo' => 'METRO', 'simbolo' => 'm']);
    }

    public function test_unit_can_be_edited_without_references_and_creator_is_preserved(): void
    {
        $creador = Usuario::factory()->create(['rol_id' => 1]);
        $editor = Usuario::factory()->create(['rol_id' => 2]);
        $unidad = UnidadMedida::factory()->create(['creado_por' => $creador->id]);
        $this->signIn($editor)->get(route('unidades-medida.edit', $unidad))->assertOk();
        $this->put(route('unidades-medida.update', $unidad), $this->detalle(['codigo' => 'EDITADA', 'nombre' => 'Unidad editada', 'simbolo' => 'L', 'dimension' => 'volume', 'decimales' => 6, 'creado_por' => $editor->id]))->assertRedirect();
        $fresh = $unidad->fresh();
        $this->assertSame('EDITADA', $fresh->codigo);
        $this->assertSame('L', $fresh->simbolo);
        $this->assertSame('volume', $fresh->dimension);
        $this->assertSame(6, $fresh->decimales);
        $this->assertSame($creador->id, $fresh->creado_por);
        $this->assertSame($editor->id, $fresh->modificador->id);
        $this->put(route('unidades-medida.update', $unidad), $this->detalle(['codigo' => 'EDITADA', 'simbolo' => 'L', 'dimension' => 'volume', 'decimales' => 6]))->assertRedirect();
    }

    public function test_used_unit_cannot_change_code_symbol_dimension_or_precision_but_name_can_change(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $data = $unidad->only(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo']);
        $this->partialMock(ReferenciasCatalogo::class)->shouldReceive('unitHasAnyReferences')->times(4)->with($unidad->id)->andReturn(true);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach ([['codigo' => 'OTRA'], ['simbolo' => 'L'], ['dimension' => 'volume'], ['decimales' => 1]] as $changes) {
            $this->put(route('unidades-medida.update', $unidad), array_replace($data, $changes))->assertSessionHasErrors('unidad_medida');
        }
        $this->assertSame($data, $unidad->fresh()->only(array_keys($data)));
        $this->put(route('unidades-medida.update', $unidad), array_replace($data, ['nombre' => 'Nombre aclarado']))->assertRedirect();
        $this->assertSame('Nombre aclarado', $unidad->fresh()->nombre);
    }

    public function test_active_references_block_status_patch_and_form_deactivation(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $this->partialMock(ReferenciasCatalogo::class)->shouldReceive('unitHasActiveReferences')->twice()->with($unidad->id)->andReturn(true);
        $this->signIn(Usuario::factory()->create(['rol_id' => 2]));
        $this->patch(route('unidades-medida.estado', $unidad), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->put(route('unidades-medida.update', $unidad), array_replace($unidad->only(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales']), ['activo' => 0]))->assertSessionHasErrors('activo');
        $this->assertTrue($unidad->fresh()->activo);
    }

    public function test_units_are_deactivated_reactivated_and_never_deleted_or_reused(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $this->signIn(Usuario::factory()->create(['rol_id' => 2]));
        $this->patch(route('unidades-medida.estado', $unidad), ['activo' => 0, 'decimales' => 6])->assertRedirect();
        $this->assertFalse($unidad->fresh()->activo);
        $this->assertSame(0, $unidad->fresh()->decimales);
        $this->post(route('unidades-medida.store'), $this->detalle(['simbolo' => $unidad->simbolo]))->assertSessionHasErrors('simbolo');
        $this->patch(route('unidades-medida.estado', $unidad), ['activo' => 1])->assertRedirect();
        $this->delete('/unidades-de-medida/'.$unidad->id)->assertStatus(405);
        $this->assertDatabaseHas('unidades_medida', ['id' => $unidad->id, 'activo' => true]);
    }

    public function test_unit_search_status_and_pagination_are_consistent(): void
    {
        UnidadMedida::factory()->count(22)->create(['nombre' => 'Coincidencia', 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        $this->get(route('unidades-medida.index', ['q' => 'Coincidencia', 'estado' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('estado=inactive', false);
        $this->get(route('unidades-medida.index', ['estado' => 'active']))->assertSee('No se encontraron unidades de medida.');
        $unidad = UnidadMedida::factory()->create(['nombre' => 'Identidad única', 'simbolo' => 'LIT']);
        foreach ([$unidad->codigo, $unidad->nombre, $unidad->simbolo] as $search) {
            $this->get(route('unidades-medida.index', ['q' => $search]))->assertOk()->assertSee('Identidad única');
        }
        $this->get(route('unidades-medida.index', ['estado' => 'wrong']))->assertSessionHasErrors('estado');
    }

    public function test_duplicate_unit_updates_are_rejected_without_partial_changes(): void
    {
        $unidad = UnidadMedida::factory()->create();
        $other = UnidadMedida::factory()->create(['activo' => false]);
        $data = $unidad->only(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo']);
        $this->signIn(Usuario::factory()->create(['rol_id' => 2]));
        $this->put(route('unidades-medida.update', $unidad), array_replace($data, ['codigo' => $other->codigo, 'nombre' => 'No persistir']))->assertSessionHasErrors('codigo');
        $this->put(route('unidades-medida.update', $unidad), array_replace($data, ['simbolo' => $other->simbolo]))->assertSessionHasErrors('simbolo');
        $this->assertSame($data, $unidad->fresh()->only(array_keys($data)));
    }

    public function test_unit_names_and_symbols_are_escaped_in_blade(): void
    {
        UnidadMedida::factory()->create(['nombre' => '<script>alert(1)</script>', 'simbolo' => '<b>X</b>']);
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]))->get(route('unidades-medida.index'))->assertOk()->assertSee('&lt;script&gt;', false)->assertSee('&lt;b&gt;X&lt;/b&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>X</b>', false);
    }
}
