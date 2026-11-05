<?php

namespace Tests\Feature\Catalog;

use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\TestCase;

class IndividualUnitManagementTest extends TestCase
{
    use CreatesIndividualUnitCatalog;

    public function test_managers_register_search_edit_and_toggle_without_stock_side_effects(): void
    {
        foreach ([1, 2] as $rol) {
            $actor = Usuario::factory()->create(['rol_id' => $rol]);
            $data = $this->individualData(['identificador' => ' ind-'.$rol.' ', 'numero_serie' => ' Serie-'.$rol.' ']);
            $ubicacion = Ubicacion::factory()->create();
            UbicacionProducto::factory()->create(['producto_id' => $data['producto_id'], 'ubicacion_id' => $ubicacion->id]);
            $data['ubicacion_id'] = $ubicacion->id;
            $this->signIn($actor)->get(route('unidades-individuales.create', ['producto_id' => $data['producto_id']]))->assertOk()->assertSee('Nueva unidad individualizada');
            $this->post(route('unidades-individuales.store'), $data + ['creado_por' => 999999, 'modificado_por' => 999999, 'version_bloqueo' => 999])->assertSessionHasNoErrors();
            $unidad = UnidadIndividual::where('identificador', 'IND-'.$rol)->firstOrFail();
            $this->assertSame($actor->id, $unidad->creado_por);
            $this->assertSame(1, $unidad->version_bloqueo);
            $this->assertSame('Serie-'.$rol, $unidad->numero_serie);
            $this->assertCount(1, $unidad->producto->unidadesIndividuales);
            $this->assertCount(1, $unidad->ubicacion->unidadesIndividuales);
            $this->get(route('unidades-individuales.index', ['q' => 'Serie-'.$rol, 'producto_id' => $unidad->producto_id, 'ubicacion_id' => $ubicacion->id, 'estado' => 'active']))->assertOk()->assertSee($unidad->identificador);
            $this->get(route('unidades-individuales.index', ['q' => $unidad->identificador]))->assertOk()->assertSee($unidad->identificador);
            $this->get(route('unidades-individuales.show', $unidad))->assertOk()->assertSee('Sin observaciones');
            $this->get(route('unidades-individuales.edit', $unidad))->assertOk();
            $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, ['observaciones' => '<script>test</script>']))->assertSessionHasNoErrors();
            $this->get(route('unidades-individuales.show', $unidad))->assertSee('&lt;script&gt;test&lt;/script&gt;', false)->assertDontSee('<script>test</script>', false);
            foreach ([0, 1] as $state) {
                $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => $state, 'version_bloqueo' => $unidad->fresh()->version_bloqueo])->assertSessionHasNoErrors();
                $this->assertSame((bool) $state, $unidad->fresh()->activo);
            }
            $this->delete(route('unidades-individuales.show', $unidad))->assertStatus(405);
        }
        foreach (['stocks', 'movements', 'loans'] as $table) {
            $this->assertFalse(Schema::hasTable($table));
        }
    }

    public function test_required_fields_types_lengths_and_product_mode_are_enforced(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $data = $this->individualData();
        foreach (['identificador', 'producto_id', 'activo'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post(route('unidades-individuales.store'), $invalid)->assertSessionHasErrors($field);
        }
        foreach ([['identificador' => ' '], ['identificador' => str_repeat('A', 65)], ['numero_serie' => str_repeat('S', 101)], ['numero_serie' => []], ['observaciones' => str_repeat('N', 10001)], ['activo' => 2], ['producto_id' => 999999], ['ubicacion_id' => 999999]] as $changes) {
            $this->post(route('unidades-individuales.store'), array_replace($data, $changes))->assertSessionHasErrors();
        }
        foreach ([['modo_control' => 'quantity'], ['modo_control' => 'individual', 'activo' => false]] as $state) {
            $this->post(route('unidades-individuales.store'), array_replace($data, ['producto_id' => Producto::factory()->create($state)->id]))->assertSessionHasErrors('producto_id');
        }
        $this->assertDatabaseCount('unidades_individuales', 0);
        $this->get(route('unidades-individuales.index', ['estado' => 'other']))->assertSessionHasErrors('estado');
    }

    public function test_series_are_optional_case_sensitive_unique_per_product_and_zero_is_not_empty(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $this->signIn($actor);
        $base = $this->individualData();
        $index = 0;
        foreach ([null, '', '  ', 'S-a', 'S-A', '0'] as $serial) {
            $this->post(route('unidades-individuales.store'), array_replace($base, ['identificador' => 'SERIES-'.chr(65 + $index++), 'numero_serie' => $serial]))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('unidades_individuales', 6);
        $this->assertSame(3, UnidadIndividual::whereNull('numero_serie')->count());
        $this->assertSame(1, UnidadIndividual::where('numero_serie', '0')->count());
        $this->get(route('unidades-individuales.index', ['q' => '0']))->assertViewHas('unidades', fn ($unidades) => $unidades->total() === 1);
        $unidad = UnidadIndividual::where('numero_serie', 'S-a')->firstOrFail();
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 0, 'version_bloqueo' => 1])->assertSessionHasNoErrors();
        $this->post(route('unidades-individuales.store'), array_replace($base, ['identificador' => 'OTHER', 'numero_serie' => 'S-a']))->assertSessionHasErrors('numero_serie');
        $this->post(route('unidades-individuales.store'), $this->individualData(['numero_serie' => 'S-a']))->assertSessionHasNoErrors();
        $this->post(route('unidades-individuales.store'), array_replace($base, ['identificador' => strtolower($unidad->identificador)]))->assertSessionHasErrors('identificador');
    }

    public function test_initial_location_requires_active_storable_location_and_matching_active_link(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $data = $this->individualData();
        foreach ([[], ['activo' => false], ['permite_almacenar' => false]] as $state) {
            $ubicacion = Ubicacion::factory()->create($state);
            if ($state) {
                UbicacionProducto::factory()->create(['producto_id' => $data['producto_id'], 'ubicacion_id' => $ubicacion->id]);
            }
            $this->post(route('unidades-individuales.store'), array_replace($data, ['ubicacion_id' => $ubicacion->id]))->assertSessionHasErrors('ubicacion_id');
        }
        $ubicacion = Ubicacion::factory()->create();
        UbicacionProducto::factory()->create(['producto_id' => $data['producto_id'], 'ubicacion_id' => $ubicacion->id, 'activo' => false]);
        $this->post(route('unidades-individuales.store'), array_replace($data, ['ubicacion_id' => $ubicacion->id]))->assertSessionHasErrors('ubicacion_id');
        $other = Ubicacion::factory()->create();
        UbicacionProducto::factory()->create(['ubicacion_id' => $other->id]);
        $this->post(route('unidades-individuales.store'), array_replace($data, ['ubicacion_id' => $other->id]))->assertSessionHasErrors('ubicacion_id');
        $padre = Ubicacion::factory()->create(['activo' => false]);
        $child = Ubicacion::factory()->create(['padre_id' => $padre->id]);
        UbicacionProducto::factory()->create(['producto_id' => $data['producto_id'], 'ubicacion_id' => $child->id]);
        $this->post(route('unidades-individuales.store'), array_replace($data, ['ubicacion_id' => $child->id]))->assertSessionHasErrors();
        $this->assertDatabaseCount('unidades_individuales', 0);
    }

    public function test_identity_product_and_initial_location_are_fixed_and_stale_edits_fail(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $unidad = $this->savedIndividual($actor);
        $this->signIn($actor);
        foreach ([['identificador' => 'NEW'], ['producto_id' => Producto::factory()->create(['modo_control' => 'individual'])->id], ['ubicacion_id' => Ubicacion::factory()->create()->id]] as $changes) {
            $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, $changes))->assertSessionHasErrors(array_key_first($changes));
        }
        $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, ['numero_serie' => 'S-1', 'observaciones' => 'Primera']))->assertSessionHasNoErrors();
        $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, ['observaciones' => 'Obsoleta']))->assertStatus(409);
        $unidad->refresh();
        foreach ([null, 'S-2'] as $serial) {
            $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, ['numero_serie' => $serial]))->assertSessionHasErrors('numero_serie');
        }
        $this->assertSame('Primera', $unidad->fresh()->observaciones);
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 0])->assertSessionHasErrors('version_bloqueo');
    }
}
