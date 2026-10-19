<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveIndividualUnit;
use App\Actions\Roles\SaveRole;
use App\Models\IndividualUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\CreatesIndividualUnitCatalog;
use Tests\TestCase;

class IndividualUnitAuthorizationTest extends TestCase
{
    use CreatesIndividualUnitCatalog;

    public function test_auditor_reads_and_direct_mutations_are_forbidden(): void
    {
        $unit = IndividualUnit::factory()->create();
        $this->signIn(User::factory()->create(['role_id' => 3]));
        $this->get(route('individual-units.index'))->assertOk();
        $this->get(route('individual-units.show', $unit))->assertOk();
        $this->get(route('individual-units.create'))->assertForbidden();
        $this->get(route('individual-units.edit', $unit))->assertForbidden();
        $this->post(route('individual-units.store'), $this->individualData())->assertForbidden();
        $this->put(route('individual-units.update', $unit), $this->individualPayload($unit))->assertForbidden();
        $this->patch(route('individual-units.status', $unit), ['is_active' => 0, 'lock_version' => 1])->assertForbidden();
        $this->assertTrue($unit->fresh()->is_active);
        $this->get(route('dashboard'))->assertDontSee('Registrar unidad');
    }

    public function test_custom_role_names_grant_nothing_and_update_does_not_grant_status(): void
    {
        $role = Role::create(['code' => 'individual_custom', 'name' => 'Administrador']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $unit = IndividualUnit::factory()->create();
        $this->signIn($actor)->get(route('individual-units.index'))->assertForbidden();
        $role->permissions()->syncWithPivotValues(Permission::whereIn('code', ['panel.view', 'individual_units.view', 'individual_units.update'])->pluck('id'), ['is_active' => true]);
        $this->signIn($actor->fresh())->put(route('individual-units.update', $unit), $this->individualPayload($unit, ['is_active' => 0]))->assertForbidden();
        $this->put(route('individual-units.update', $unit), $this->individualPayload($unit, ['notes' => 'Permitido']))->assertSessionHasNoErrors();
        $this->assertTrue($unit->fresh()->is_active);
    }

    public function test_revocation_invalidates_sessions_and_stale_action_actor(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $actor = User::factory()->create(['role_id' => 2]);
        $actor->load('role');
        $this->signIn($actor);
        $codes = array_diff($actor->role->assignedPermissionCodes(), ['individual_units.create']);
        app(SaveRole::class)->handle($admin, $actor->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->getJson(route('individual-units.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->post(route('individual-units.store'), $this->individualData())->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(SaveIndividualUnit::class)->handle($actor, null, $this->individualData());
    }

    public function test_guests_inactive_accounts_and_csrf_are_protected(): void
    {
        $unit = IndividualUnit::factory()->create();
        $this->getJson(route('individual-units.index'))->assertUnauthorized();
        $this->postJson(route('individual-units.store'), [])->assertUnauthorized();
        $actor = User::factory()->create(['role_id' => 1]);
        $this->signIn($actor);
        $actor->update(['is_active' => false]);
        $this->getJson(route('individual-units.index'))->assertUnauthorized();
        $actor->update(['is_active' => true]);
        $this->signIn($actor->fresh());
        $this->app->instance('env', 'local');
        $this->post(route('individual-units.store'), $this->individualData())->assertStatus(419);
        $this->put(route('individual-units.update', $unit), $this->individualPayload($unit))->assertStatus(419);
        $this->patch(route('individual-units.status', $unit), ['is_active' => 0, 'lock_version' => 1])->assertStatus(419);
    }
}
