<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarUbicacion;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use App\Services\JerarquiaUbicaciones;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    private function detalle(array $changes = []): array
    {
        return array_replace(['codigo' => 'SEC-A', 'nombre' => 'Sector A', 'tipo' => 'sector', 'padre_id' => null, 'permite_almacenar' => 1, 'descripcion' => 'Área de almacenamiento', 'activo' => 1], $changes);
    }

    public function test_administrator_and_manager_create_edit_and_toggle_with_server_authors(): void
    {
        foreach ([1, 2] as $rol) {
            $actor = Usuario::factory()->create(['rol_id' => $rol]);
            $this->signIn($actor)->get(route('ubicaciones.create'))->assertOk()->assertSee('Nueva ubicación');
            $this->post(route('ubicaciones.store'), $this->detalle(['codigo' => ' sec-'.$rol.' ', 'creado_por' => 999, 'modificado_por' => 999]))->assertRedirect(route('ubicaciones.index'))->assertSessionHasNoErrors();
            $ubicacion = Ubicacion::where('codigo', 'SEC-'.$rol)->firstOrFail();
            $this->assertSame($actor->id, $ubicacion->creador->id);
            $this->assertSame($actor->id, $ubicacion->modificador->id);
            $this->get(route('ubicaciones.edit', $ubicacion))->assertOk();
            $this->put(route('ubicaciones.update', $ubicacion), $this->detalle(['codigo' => $ubicacion->codigo, 'nombre' => 'Nombre corregido', 'permite_almacenar' => 0]))->assertSessionHasNoErrors();
            $this->patch(route('ubicaciones.estado', $ubicacion), ['activo' => 0, 'codigo' => 'ALTERADO', 'permite_almacenar' => 1])->assertSessionHasNoErrors();
            $this->assertFalse($ubicacion->fresh()->activo);
            $this->assertFalse($ubicacion->fresh()->permite_almacenar);
            $this->assertSame('Nombre corregido', $ubicacion->fresh()->nombre);
            $this->patch(route('ubicaciones.estado', $ubicacion), ['activo' => 1])->assertSessionHasNoErrors();
            $this->assertTrue($ubicacion->fresh()->activo);
            $this->delete('/ubicaciones/'.$ubicacion->id)->assertStatus(405);
            $this->assertDatabaseHas('ubicaciones', ['id' => $ubicacion->id, 'codigo' => 'SEC-'.$rol]);
        }
    }

    public function test_three_types_hierarchy_and_non_storable_parent_are_supported(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $padre = null;
        foreach (Ubicacion::TYPES as $tipo => $label) {
            $this->post(route('ubicaciones.store'), $this->detalle(['codigo' => $tipo, 'nombre' => $label, 'tipo' => $tipo, 'padre_id' => $padre?->id, 'permite_almacenar' => 0]))->assertSessionHasNoErrors();
            $node = Ubicacion::where('codigo', strtoupper($tipo))->firstOrFail();
            $this->assertSame($padre?->id, $node->padre_id);
            if ($padre) {
                $this->assertSame($padre->id, $node->padre->id);
                $this->assertSame($node->id, $padre->hijos()->firstOrFail()->id);
            }
            $padre = $node;
        }
        $this->get(route('ubicaciones.tree'))->assertOk()->assertSeeInOrder(['SECTOR · Sector', 'SHELF · Estante', 'CABINET · Gabinete'])->assertSee('SECTOR · Sector → SHELF · Estante → CABINET · Gabinete');
    }

    public function test_fields_types_flags_and_lengths_are_validated_in_spanish(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['codigo', 'nombre', 'tipo', 'permite_almacenar', 'activo'] as $field) {
            $data = $this->detalle();
            unset($data[$field]);
            $this->post(route('ubicaciones.store'), $data)->assertSessionHasErrors($field);
        }
        foreach ([['codigo' => '  '], ['nombre' => '  '], ['codigo' => str_repeat('x', 61)], ['nombre' => str_repeat('x', 151)], ['descripcion' => str_repeat('x', 10001)], ['tipo' => 'Sector'], ['tipo' => 'aisle'], ['padre_id' => 999999], ['permite_almacenar' => 2], ['activo' => 2]] as $changes) {
            $this->post(route('ubicaciones.store'), $this->detalle($changes))->assertSessionHasErrors();
        }
        $this->post(route('ubicaciones.store'), $this->detalle(['nombre' => '']))->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);
        $this->assertDatabaseCount('ubicaciones', 0);
    }

    public function test_inactive_code_is_not_reused_but_names_may_repeat(): void
    {
        $ubicacion = Ubicacion::factory()->create(['codigo' => 'SEC-A', 'nombre' => 'Repetido', 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('ubicaciones.store'), $this->detalle(['codigo' => ' sec-a ']))->assertSessionHasErrors('codigo');
        $this->post(route('ubicaciones.store'), $this->detalle(['codigo' => 'OTRO', 'nombre' => 'Repetido']))->assertSessionHasNoErrors();
        $this->put(route('ubicaciones.update', $ubicacion), $this->detalle(['codigo' => $ubicacion->codigo, 'nombre' => 'Repetido', 'activo' => 0]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('ubicaciones', 2);
    }

    public function test_self_and_descendants_including_inactive_nodes_are_rejected_without_partial_edits(): void
    {
        $root = Ubicacion::factory()->create();
        $child = Ubicacion::factory()->create(['padre_id' => $root->id, 'activo' => false]);
        $grandchild = Ubicacion::factory()->create(['padre_id' => $child->id, 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach ([$root, $child, $grandchild] as $padre) {
            $this->put(route('ubicaciones.update', $root), $this->detalle(['codigo' => $root->codigo, 'padre_id' => $padre->id, 'nombre' => 'No persistir']))->assertSessionHasErrors('padre_id');
            $this->assertNull($root->fresh()->padre_id);
            $this->assertNotSame('No persistir', $root->fresh()->nombre);
        }
    }

    public function test_inactive_parents_ancestors_and_reactivation_are_checked_with_historical_exception(): void
    {
        $root = Ubicacion::factory()->create(['activo' => false]);
        $child = Ubicacion::factory()->create(['padre_id' => $root->id, 'activo' => false]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->post(route('ubicaciones.store'), $this->detalle(['padre_id' => $root->id, 'activo' => 0]))->assertSessionHasErrors('padre_id');
        $this->patch(route('ubicaciones.estado', $child), ['activo' => 1])->assertSessionHasErrors('padre_id');
        $this->put(route('ubicaciones.update', $child), $this->detalle(['codigo' => $child->codigo, 'padre_id' => $root->id, 'activo' => 0, 'nombre' => 'Corrección histórica']))->assertSessionHasNoErrors();
        $this->get(route('ubicaciones.edit', $child))->assertOk()->assertSee('Padre histórico inactivo');
        $this->assertSame($root->id, $child->fresh()->padre_id);
        // Simulated pre-existing inconsistency: an active padre under an inactive ancestor.
        $child->activo = true;
        $child->save();
        $this->post(route('ubicaciones.store'), $this->detalle(['padre_id' => $child->id]))->assertSessionHasErrors('padre_id');
        $this->patch(route('ubicaciones.estado', $child), ['activo' => 0])->assertSessionHasNoErrors();
        $this->patch(route('ubicaciones.estado', $root), ['activo' => 1])->assertSessionHasNoErrors();
        $this->patch(route('ubicaciones.estado', $child), ['activo' => 1])->assertSessionHasNoErrors();
    }

    public function test_active_children_block_both_deactivation_endpoints_and_inactive_links_are_kept(): void
    {
        $root = Ubicacion::factory()->create();
        $child = Ubicacion::factory()->create(['padre_id' => $root->id]);
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->patch(route('ubicaciones.estado', $root), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->put(route('ubicaciones.update', $root), $this->detalle(['codigo' => $root->codigo, 'activo' => 0, 'nombre' => 'No persistir']))->assertSessionHasErrors('activo');
        $this->assertTrue($root->fresh()->activo);
        $this->patch(route('ubicaciones.estado', $child), ['activo' => 0])->assertSessionHasNoErrors();
        $this->patch(route('ubicaciones.estado', $root), ['activo' => 0])->assertSessionHasNoErrors();
        $this->assertSame($root->id, $child->fresh()->padre_id);
        $this->assertFalse($root->fresh()->activo);
    }

    public function test_parent_options_exclude_self_and_descendants_and_omit_inactive_locations(): void
    {
        $root = Ubicacion::factory()->create(['nombre' => 'Propia']);
        Ubicacion::factory()->create(['padre_id' => $root->id, 'nombre' => 'Descendiente']);
        Ubicacion::factory()->create(['nombre' => 'Inactiva ajena', 'activo' => false]);
        $allowed = Ubicacion::factory()->create(['nombre' => 'Padre permitido']);
        $response = $this->signIn(Usuario::factory()->create(['rol_id' => 1]))->get(route('ubicaciones.edit', $root))->assertOk();
        $response->assertSee('Padre permitido')->assertDontSee('Descendiente')->assertDontSee('Inactiva ajena');
        $response->assertDontSee('<option value="'.$root->id.'"', false);
        $this->put(route('ubicaciones.update', $root), $this->detalle(['codigo' => $root->codigo, 'padre_id' => $allowed->id]))->assertSessionHasNoErrors();
        $this->assertSame($allowed->id, $root->fresh()->padre_id);
    }

    public function test_real_inactive_units_protect_location_and_subtree_history(): void
    {
        $root = Ubicacion::factory()->create();
        $other = Ubicacion::factory()->create();
        $child = Ubicacion::factory()->create(['padre_id' => $root->id, 'activo' => false]);
        foreach ([$root, $child] as $node) {
            $unidad = UnidadIndividual::factory()->create();
            UbicacionProducto::factory()->create(['producto_id' => $unidad->producto_id, 'ubicacion_id' => $node->id, 'activo' => false]);
            $unidad->update(['ubicacion_id' => $node->id, 'activo' => false]);
        }
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->patch(route('ubicaciones.estado', $root), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->put(route('ubicaciones.update', $root), $this->detalle(['codigo' => $root->codigo, 'padre_id' => $other->id]))->assertSessionHasErrors('ubicacion');
        $this->put(route('ubicaciones.update', $root), $this->detalle(['codigo' => $root->codigo, 'tipo' => 'cabinet']))->assertSessionHasErrors('ubicacion');
        $this->put(route('ubicaciones.update', $root), $this->detalle(['codigo' => $root->codigo, 'permite_almacenar' => 0]))->assertSessionHasErrors('permite_almacenar');
        $this->assertTrue($root->fresh()->activo);
        $this->assertTrue($root->fresh()->permite_almacenar);
        $this->assertNull($root->fresh()->padre_id);
        $this->assertSame('sector', $root->fresh()->tipo);
    }

    public function test_search_filters_pagination_literal_wildcards_and_html_escape(): void
    {
        $padre = Ubicacion::factory()->create();
        for ($i = 0; $i < 22; $i++) {
            Ubicacion::factory()->create(['padre_id' => $padre->id, 'nombre' => 'Filtro '.$i, 'tipo' => 'shelf', 'activo' => false]);
        }
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        $this->get(route('ubicaciones.index', ['q' => 'Filtro', 'padre_id' => $padre->id, 'tipo' => 'shelf', 'estado' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('tipo=estante', false);
        $this->get(route('ubicaciones.index', ['q' => 'Filtro', 'roots_only' => 1]))->assertSee('No se encontraron ubicaciones.');
        foreach ([['tipo' => 'bad'], ['estado' => 'bad'], ['padre_id' => 999999], ['q' => str_repeat('x', 101)], ['roots_only' => 2]] as $query) {
            $this->get(route('ubicaciones.index', $query))->assertSessionHasErrors();
        }
        $node = Ubicacion::factory()->create(['codigo' => 'LOC%_', 'nombre' => '<script>alert(1)</script>', 'descripcion' => '<img src=x onerror=alert(1)>']);
        $this->get(route('ubicaciones.index', ['q' => '%_']))->assertSee($node->codigo)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('Filtro 0');
        $this->get(route('ubicaciones.tree'))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_stale_action_inputs_are_rechecked_and_duplicate_collision_is_translated(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $root = Ubicacion::factory()->create();
        Ubicacion::whereKey($root->id)->update(['activo' => false]);
        try {
            app(GuardarUbicacion::class)->handle($actor, null, $this->detalle(['padre_id' => $root->id]));
            $this->fail('La acción debe volver a consultar el padre.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('padre_id', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        app(GuardarUbicacion::class)->handle($actor, null, $this->detalle(['codigo' => $root->codigo]));
    }

    public function test_existing_cycle_is_reported_without_infinite_traversal_or_partial_write(): void
    {
        $a = Ubicacion::factory()->create();
        $b = Ubicacion::factory()->create(['padre_id' => $a->id]);
        Ubicacion::whereKey($a->id)->update(['padre_id' => $b->id]);
        foreach ([fn () => app(JerarquiaUbicaciones::class)->paths(app(JerarquiaUbicaciones::class)->catalog()), fn () => app(JerarquiaUbicaciones::class)->subtreeIds($a->id)] as $operation) {
            try {
                $operation();
                $this->fail('Debe detectarse la inconsistencia existente.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('padre_id', $exception->errors());
            }
        }
        $this->expectException(ValidationException::class);
        app(GuardarUbicacion::class)->handle(Usuario::factory()->create(['rol_id' => 1]), $a, ['nombre' => 'No persistir']);
    }
}
