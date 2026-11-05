<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\Usuario;
use App\Services\ReferenciasCatalogo;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    private function detalle(array $changes = []): array
    {
        return array_replace(['codigo' => 'HERR', 'nombre' => 'Herramientas', 'descripcion' => 'Herramientas manuales', 'activo' => 1], $changes);
    }

    public function test_administrator_and_manager_can_register_edit_and_change_status(): void
    {
        foreach ([1, 2] as $roleId) {
            $actor = Usuario::factory()->create(['rol_id' => $roleId]);
            $this->signIn($actor)->get(route('categorias.create'))->assertOk()->assertSee('Nueva categoría');
            $this->post(route('categorias.store'), $this->detalle(['codigo' => ' cat-'.$roleId.' ', 'nombre' => 'Categoría '.$roleId, 'creado_por' => 999, 'modificado_por' => 999]))->assertRedirect(route('categorias.index'));
            $categoria = Categoria::where('codigo', 'CAT-'.$roleId)->firstOrFail();
            $this->assertSame($actor->id, $categoria->creado_por);
            $this->assertSame($actor->id, $categoria->modificado_por);
            $this->assertSame($actor->id, $categoria->creador->id);
            $this->get(route('categorias.edit', $categoria))->assertOk();
            $this->put(route('categorias.update', $categoria), $this->detalle(['codigo' => $categoria->codigo, 'nombre' => 'Categoría editada '.$roleId, 'descripcion' => 'Descripción actualizada']))->assertRedirect();
            $this->patch(route('categorias.estado', $categoria), ['activo' => 0, 'nombre' => 'Manipulado'])->assertRedirect();
            $this->assertFalse($categoria->fresh()->activo);
            $this->assertSame('Categoría editada '.$roleId, $categoria->fresh()->nombre);
            $this->patch(route('categorias.estado', $categoria), ['activo' => 1])->assertRedirect();
            $this->assertTrue($categoria->fresh()->activo);
        }
    }

    public function test_category_fields_are_required_and_lengths_and_state_are_validated_in_spanish(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['codigo', 'nombre', 'activo'] as $field) {
            $detalle = $this->detalle();
            unset($detalle[$field]);
            $this->post(route('categorias.store'), $detalle)->assertSessionHasErrors($field);
        }
        foreach ([['codigo' => '   '], ['nombre' => '   '], ['codigo' => str_repeat('x', 51)], ['nombre' => str_repeat('x', 151)], ['descripcion' => str_repeat('x', 10001)], ['activo' => 2]] as $changes) {
            $this->post(route('categorias.store'), $this->detalle($changes))->assertSessionHasErrors();
        }
        $this->post(route('categorias.store'), $this->detalle(['nombre' => '']))->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);
        $this->assertDatabaseCount('categorias', 0);
    }

    public function test_duplicate_codes_and_names_are_rejected_even_when_inactive(): void
    {
        $existing = Categoria::factory()->create(['codigo' => 'HERR', 'nombre' => 'Herramientas', 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('categorias.store'), $this->detalle(['codigo' => ' herr ', 'nombre' => 'Otra']))->assertSessionHasErrors('codigo');
        $this->post(route('categorias.store'), $this->detalle(['codigo' => 'OTRA', 'nombre' => 'herramientas']))->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('categorias', 1);
        $this->assertFalse($existing->fresh()->activo);
    }

    public function test_editing_ignores_own_unique_fields_and_preserves_creator(): void
    {
        $creador = Usuario::factory()->create(['rol_id' => 1]);
        $editor = Usuario::factory()->create(['rol_id' => 2]);
        $categoria = Categoria::factory()->create(['creado_por' => $creador->id, 'modificado_por' => $creador->id]);
        $other = Categoria::factory()->create();
        $this->signIn($editor)->put(route('categorias.update', $categoria), $this->detalle(['codigo' => $categoria->codigo, 'nombre' => $categoria->nombre, 'creado_por' => $editor->id]))->assertRedirect();
        $this->assertSame($creador->id, $categoria->fresh()->creado_por);
        $this->assertSame($editor->id, $categoria->fresh()->modificador->id);
        $this->put(route('categorias.update', $categoria), $this->detalle(['codigo' => $other->codigo]))->assertSessionHasErrors('codigo');
        $this->put(route('categorias.update', $categoria), $this->detalle(['codigo' => $categoria->codigo, 'nombre' => $other->nombre]))->assertSessionHasErrors('nombre');
    }

    public function test_search_filter_and_pagination_keep_filters_and_treat_wildcards_literally(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        Categoria::factory()->count(22)->sequence(fn ($sequence) => ['nombre' => 'Filtro categoría '.$sequence->index, 'activo' => false])->create();
        $this->get(route('categorias.index', ['q' => 'Filtro', 'estado' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('estado=inactive', false);
        $this->get(route('categorias.index', ['q' => 'Filtro', 'estado' => 'active']))->assertOk()->assertSee('No se encontraron categorías.');
        $categoria = Categoria::factory()->create(['codigo' => 'CAT%_ESPECIAL']);
        $this->get(route('categorias.index', ['q' => '%_']))->assertOk()->assertSee($categoria->nombre)->assertDontSee('Filtro categoría');
        $this->get(route('categorias.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
        $this->get(route('categorias.index', ['estado' => 'wrong']))->assertSessionHasErrors('estado');
    }

    public function test_deactivation_dependency_guard_blocks_both_endpoints_and_keeps_data(): void
    {
        $categoria = Categoria::factory()->create();
        $this->partialMock(ReferenciasCatalogo::class)->shouldReceive('categoryHasActiveProducts')->twice()->with($categoria->id)->andReturn(true);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->patch(route('categorias.estado', $categoria), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->put(route('categorias.update', $categoria), $this->detalle(['codigo' => $categoria->codigo, 'nombre' => 'Cambio rechazado', 'activo' => 0]))->assertSessionHasErrors('activo');
        $this->assertTrue($categoria->fresh()->activo);
        $this->assertNotSame('Cambio rechazado', $categoria->fresh()->nombre);
    }

    public function test_without_future_tables_deactivation_is_allowed_and_records_are_retained(): void
    {
        $categoria = Categoria::factory()->create();
        $this->assertFalse(app(ReferenciasCatalogo::class)->categoryHasActiveProducts($categoria->id));
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]))->patch(route('categorias.estado', $categoria), ['activo' => 0])->assertRedirect();
        $this->delete('/categorias/'.$categoria->id)->assertStatus(405);
        $this->assertDatabaseHas('categorias', ['id' => $categoria->id, 'codigo' => $categoria->codigo]);
    }

    public function test_category_html_is_escaped(): void
    {
        Categoria::factory()->create(['nombre' => '<script>alert(1)</script>', 'descripcion' => '<img src=x onerror=alert(1)>']);
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]))->get(route('categorias.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
    }
}
