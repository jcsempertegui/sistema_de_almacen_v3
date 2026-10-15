<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveLocation;
use App\Actions\Roles\SaveRole;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class LocationAuthorizationTest extends TestCase
{
    private function customActor(array $codes): User
    {
        $role = Role::create(['code' => 'loc_'.bin2hex(random_bytes(4)), 'name' => 'Administrador']);
        $role->permissions()->attach(Permission::whereIn('code', $codes)->pluck('id')->all(), ['is_active' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function data(Location $node): array
    {
        return $node->only(['code', 'name', 'type', 'parent_id', 'is_storable', 'description', 'is_active']);
    }

    public function test_auditor_can_read_index_and_tree_but_all_direct_mutations_are_forbidden(): void
    {
        $node = Location::factory()->create();
        $this->signIn(User::factory()->create(['role_id' => 3]));
        $this->get(route('locations.index'))->assertOk()->assertDontSee('Editar')->assertDontSee('Desactivar')->assertDontSee('Nueva ubicación');
        $this->get(route('locations.tree'))->assertOk();
        $this->get(route('locations.create'))->assertForbidden();
        $this->get(route('locations.edit', $node))->assertForbidden();
        $this->post(route('locations.store'), $this->data($node))->assertForbidden();
        $this->put(route('locations.update', $node), $this->data($node))->assertForbidden();
        $this->patch(route('locations.status', $node), ['is_active' => 0])->assertForbidden();
        $this->assertTrue($node->fresh()->is_active);
    }

    public function test_guests_and_inactive_users_cannot_access_locations(): void
    {
        $node = Location::factory()->create();
        $this->get(route('locations.index'))->assertRedirect(route('login'));
        $this->get(route('locations.tree'))->assertRedirect(route('login'));
        $this->post(route('locations.store'), $this->data($node))->assertRedirect(route('login'));
        $inactive = User::factory()->create(['role_id' => 1, 'is_active' => false]);
        $this->signIn($inactive)->getJson(route('locations.index'))->assertUnauthorized();
    }

    public function test_each_action_requires_its_permission_even_with_an_administrator_role_name(): void
    {
        $node = Location::factory()->create();
        $data = $this->data($node);
        $this->signIn($this->customActor(['locations.view', 'locations.update']));
        $this->get(route('locations.index'))->assertOk()->assertSee('Editar')->assertDontSee('Desactivar');
        $this->get(route('locations.edit', $node))->assertOk();
        $this->put(route('locations.update', $node), array_replace($data, ['name' => 'Corrección permitida']))->assertSessionHasNoErrors();
        $this->post(route('locations.store'), $data)->assertForbidden();
        $this->patch(route('locations.status', $node), ['is_active' => 0])->assertForbidden();
        $this->put(route('locations.update', $node), array_replace($data, ['is_active' => 0]))->assertForbidden();
        $this->signIn($this->customActor([]))->get(route('locations.index'))->assertForbidden();
        $this->get(route('locations.index', ['parent_id' => 999999, 'q' => str_repeat('x', 101)]))->assertForbidden();
        $this->get(route('locations.tree'))->assertForbidden();
        $this->assertTrue($node->fresh()->is_active);
    }

    public function test_create_status_and_metadata_permissions_are_independent(): void
    {
        $node = Location::factory()->create();
        $this->signIn($this->customActor(['locations.create', 'locations.change_status']));
        $this->get(route('locations.create'))->assertOk();
        $this->post(route('locations.store'), array_replace($this->data($node), ['code' => 'NUEVA']))->assertSessionHasNoErrors();
        $this->get(route('locations.edit', $node))->assertForbidden();
        $this->put(route('locations.update', $node), $this->data($node))->assertForbidden();
        $this->patch(route('locations.status', $node), ['is_active' => 0, 'name' => 'Manipulado', 'parent_id' => 999999, 'is_storable' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($node->fresh()->is_active);
        $this->assertSame($node->name, $node->fresh()->name);
        $this->assertNull($node->fresh()->parent_id);
        $this->assertTrue($node->fresh()->is_storable);
    }

    public function test_revocation_invalidates_sessions_and_rejects_an_in_flight_action(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $actor = User::factory()->create(['role_id' => 2]);
        $actor->load('role');
        $node = Location::factory()->create();
        $this->signIn($actor);
        $codes = array_diff($actor->role->assignedPermissionCodes(), ['locations.update']);
        app(SaveRole::class)->handle($admin, $actor->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->putJson(route('locations.update', $node), $this->data($node))->assertUnauthorized();
        try {
            app(SaveLocation::class)->handle($actor, $node, ['name' => 'No persistir']);
            $this->fail('El cambio de acceso debe invalidar el operador anterior.');
        } catch (AuthorizationException) {
            $this->assertNotSame('No persistir', $node->fresh()->name);
        }
        $this->signIn($actor->fresh())->put(route('locations.update', $node), $this->data($node))->assertForbidden();
    }

    public function test_forms_require_csrf_and_new_permissions_are_configurable_with_existing_roles(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $actor = $this->customActor([]);
        $this->signIn($admin)->get(route('roles.edit', $actor->role))->assertOk()->assertSee('Ubicaciones')->assertSee('locations.change_status');
        $this->put(route('roles.update', $actor->role), ['permissions' => Permission::whereIn('code', ['panel.view', 'locations.view'])->pluck('id')->all()])->assertRedirect();
        $this->signIn($actor->fresh())->get(route('dashboard'))->assertSee('Consultar ubicaciones');
        $this->get(route('locations.tree'))->assertOk();
        $this->get(route('locations.create'))->assertForbidden();
        $this->signIn($admin);
        $this->app->instance('env', 'local');
        $this->post(route('locations.store'), ['code' => 'SIN-CSRF', 'name' => 'Sin token', 'type' => 'sector', 'is_storable' => 1, 'is_active' => 1])->assertStatus(419);
    }

    public function test_editor_preserves_creator_and_server_sets_updated_by(): void
    {
        $creator = User::factory()->create(['role_id' => 1]);
        $editor = User::factory()->create(['role_id' => 2]);
        $node = Location::factory()->create(['created_by' => $creator->id, 'updated_by' => $creator->id]);
        $this->signIn($editor)->put(route('locations.update', $node), array_replace($this->data($node), ['name' => 'Corrección', 'created_by' => $editor->id, 'updated_by' => 999999]))->assertSessionHasNoErrors();
        $this->assertSame($creator->id, $node->fresh()->created_by);
        $this->assertSame($editor->id, $node->fresh()->updated_by);
    }
}
