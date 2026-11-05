<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\TestCase;

class IndividualUnitAuthorizationTest extends TestCase
{
    use CreatesIndividualUnitCatalog;

    public function test_auditor_reads_and_direct_mutations_are_forbidden(): void
    {
        $unidad = UnidadIndividual::factory()->create();
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        $this->get(route('unidades-individuales.index'))->assertOk();
        $this->get(route('unidades-individuales.show', $unidad))->assertOk();
        $this->get(route('unidades-individuales.create'))->assertForbidden();
        $this->get(route('unidades-individuales.edit', $unidad))->assertForbidden();
        $this->post(route('unidades-individuales.store'), $this->individualData())->assertForbidden();
        $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad))->assertForbidden();
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 0, 'version_bloqueo' => 1])->assertForbidden();
        $this->assertTrue($unidad->fresh()->activo);
        $this->get(route('dashboard'))->assertDontSee('Registrar unidad');
    }

    public function test_custom_role_names_grant_nothing_and_update_does_not_grant_status(): void
    {
        $rol = Rol::create(['codigo' => 'individual_custom', 'nombre' => 'Administrador']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $unidad = UnidadIndividual::factory()->create();
        $this->signIn($actor)->get(route('unidades-individuales.index'))->assertForbidden();
        $rol->permisos()->syncWithPivotValues(Permiso::whereIn('codigo', ['panel.view', 'individual_units.view', 'individual_units.update'])->pluck('id'), ['activo' => true]);
        $this->signIn($actor->fresh())->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, ['activo' => 0]))->assertForbidden();
        $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad, ['observaciones' => 'Permitido']))->assertSessionHasNoErrors();
        $this->assertTrue($unidad->fresh()->activo);
    }

    public function test_revocation_invalidates_sessions_and_stale_action_actor(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $actor->load('rol');
        $this->signIn($actor);
        $codes = array_diff($actor->rol->codigosPermisosAsignados(), ['individual_units.create']);
        app(GuardarRol::class)->handle($admin, $actor->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->getJson(route('unidades-individuales.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->post(route('unidades-individuales.store'), $this->individualData())->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(GuardarUnidadIndividual::class)->handle($actor, null, $this->individualData());
    }

    public function test_guests_inactive_accounts_and_csrf_are_protected(): void
    {
        $unidad = UnidadIndividual::factory()->create();
        $this->getJson(route('unidades-individuales.index'))->assertUnauthorized();
        $this->postJson(route('unidades-individuales.store'), [])->assertUnauthorized();
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $this->signIn($actor);
        $actor->update(['activo' => false]);
        $this->getJson(route('unidades-individuales.index'))->assertUnauthorized();
        $actor->update(['activo' => true]);
        $this->signIn($actor->fresh());
        $this->app->instance('env', 'local');
        $this->post(route('unidades-individuales.store'), $this->individualData())->assertStatus(419);
        $this->put(route('unidades-individuales.update', $unidad), $this->individualPayload($unidad))->assertStatus(419);
        $this->patch(route('unidades-individuales.estado', $unidad), ['activo' => 0, 'version_bloqueo' => 1])->assertStatus(419);
    }
}
