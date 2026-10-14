<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveCategory;
use App\Actions\Catalog\SaveMeasurementUnit;
use App\Actions\Roles\SaveRole;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class CatalogAuthorizationTest extends TestCase
{
    private function resources(): array
    {
        $category = Category::factory()->create();
        $unit = MeasurementUnit::factory()->create();

        return [
            ['categories', 'categories', $category, $category->only(['code', 'name', 'description', 'is_active'])],
            ['measurement_units', 'measurement-units', $unit, $unit->only(['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active'])],
        ];
    }

    private function customUser(array $codes): User
    {
        $role = Role::create(['code' => 'custom_'.bin2hex(random_bytes(4)), 'name' => 'Administrador']);
        $role->permissions()->attach(Permission::whereIn('code', $codes)->pluck('id')->all(), ['is_active' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_auditor_has_read_access_but_all_direct_mutations_are_forbidden(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 3]));
        foreach ($this->resources() as [$permission, $route, $target, $data]) {
            $this->get(route($route.'.index'))->assertOk()->assertDontSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.create'))->assertForbidden();
            $this->get(route($route.'.edit', $target))->assertForbidden();
            $this->post(route($route.'.store'), $data)->assertForbidden();
            $this->put(route($route.'.update', $target), $data)->assertForbidden();
            $this->patch(route($route.'.status', $target), ['is_active' => 0])->assertForbidden();
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_guests_and_inactive_accounts_cannot_access_the_catalog(): void
    {
        foreach ($this->resources() as [$permission, $route, $target, $data]) {
            $this->get(route($route.'.index'))->assertRedirect(route('login'));
            $this->post(route($route.'.store'), $data)->assertRedirect(route('login'));
        }
        $inactive = User::factory()->create(['role_id' => 1, 'is_active' => false]);
        foreach (['categories.index', 'measurement-units.index'] as $route) {
            $this->signIn($inactive)->getJson(route($route))->assertUnauthorized();
        }
    }

    public function test_permissions_control_independent_actions_and_role_names_do_not_grant_access(): void
    {
        foreach ($this->resources() as [$permission, $route, $target, $data]) {
            $actor = $this->customUser([$permission.'.view', $permission.'.update']);
            $this->signIn($actor)->get(route($route.'.index'))->assertOk()->assertSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.edit', $target))->assertOk();
            $this->put(route($route.'.update', $target), array_replace($data, ['name' => 'Edición permitida']))->assertRedirect();
            $this->post(route($route.'.store'), $data)->assertForbidden();
            $this->patch(route($route.'.status', $target), ['is_active' => 0])->assertForbidden();
            $this->put(route($route.'.update', $target), array_replace($data, ['is_active' => 0]))->assertForbidden();
            $this->assertTrue($target->fresh()->is_active);
        }
        $this->signIn($this->customUser([]))->get(route('categories.index'))->assertForbidden();
        $this->get(route('measurement-units.index'))->assertForbidden();
    }

    public function test_status_permission_does_not_allow_editing_other_fields(): void
    {
        foreach ($this->resources() as [$permission, $route, $target, $data]) {
            $this->signIn($this->customUser([$permission.'.view', $permission.'.change_status']));
            $this->patch(route($route.'.status', $target), ['is_active' => 0, 'name' => 'Manipulado'])->assertRedirect();
            $this->assertSame($data['name'], $target->fresh()->name);
            $this->put(route($route.'.update', $target), $data)->assertForbidden();
            $this->get(route($route.'.edit', $target))->assertForbidden();
        }
    }

    public function test_revocation_invalidates_session_and_rejects_catalog_writes_in_flight(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $actor = User::factory()->create(['role_id' => 2]);
        $actor->load('role');
        $this->signIn($actor);
        $category = Category::factory()->create();
        $unit = MeasurementUnit::factory()->create();
        $codes = array_diff($actor->role->assignedPermissionCodes(), ['categories.change_status', 'measurement_units.change_status']);
        app(SaveRole::class)->handle($admin, $actor->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->patchJson(route('categories.status', $category), ['is_active' => 0])->assertUnauthorized();
        foreach ([fn () => app(SaveCategory::class)->handle($actor, $category, ['is_active' => false], statusOnly: true), fn () => app(SaveMeasurementUnit::class)->handle($actor, $unit, ['is_active' => false], statusOnly: true)] as $operation) {
            try {
                $operation();
                $this->fail('La escritura con acceso revocado debe rechazarse.');
            } catch (AuthorizationException) {
                $this->assertTrue($category->fresh()->is_active);
                $this->assertTrue($unit->fresh()->is_active);
            }
        }
        $this->signIn($actor->fresh())->patch(route('measurement-units.status', $unit), ['is_active' => 0])->assertForbidden();
    }

    public function test_catalog_post_requires_csrf(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->app->instance('env', 'local');
        $this->post(route('categories.store'), ['code' => 'SIN-CSRF', 'name' => 'Sin token', 'is_active' => 1])->assertStatus(419);
        $this->post(route('measurement-units.store'), ['code' => 'SIN-CSRF', 'name' => 'Sin token', 'symbol' => 'x', 'dimension' => 'count', 'decimal_places' => 0, 'is_active' => 1])->assertStatus(419);
    }

    public function test_permissions_are_visible_and_editable_through_existing_role_management(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $target = $this->customUser([]);
        $this->signIn($actor)->get(route('roles.edit', $target->role))->assertOk()->assertSee('Categorías')->assertSee('Unidades de medida');
        $this->put(route('roles.update', $target->role), ['permissions' => Permission::whereIn('code', ['panel.view', 'categories.view', 'measurement_units.create'])->pluck('id')->all()])->assertRedirect();
        $this->signIn($target->fresh())->get(route('categories.index'))->assertOk();
        $this->get(route('measurement-units.create'))->assertOk();
        $this->get(route('measurement-units.index'))->assertForbidden();
    }
}
