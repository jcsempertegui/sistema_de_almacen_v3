<?php

namespace Tests\Feature;

use App\Actions\Roles\SaveRole;
use App\Actions\Users\UpdateUser;
use App\Actions\Workers\SaveWorker;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role_id' => 1]);
    }

    private function role(array $codes): Role
    {
        return app(SaveRole::class)->handle($this->admin(), null, ['code' => 'delegado_'.bin2hex(random_bytes(4)), 'name' => 'Delegado', 'is_active' => true, 'permissions' => $this->ids($codes)]);
    }

    private function ids(array $codes): array
    {
        return Permission::whereIn('code', $codes)->pluck('id')->all();
    }

    public function test_worker_permissions_are_independent_for_read_create_edit_and_status(): void
    {
        $role = $this->role(['panel.view', 'workers.view', 'workers.update']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $worker = Worker::factory()->create();
        $payload = $worker->only(['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area', 'is_active']);
        $this->signIn($user)->get(route('workers.index'))->assertOk()->assertDontSee('Nuevo trabajador')->assertDontSee('Desactivar');
        $this->get(route('workers.create'))->assertForbidden();
        $this->post(route('workers.store'), $payload)->assertForbidden();
        $this->get(route('workers.edit', $worker))->assertOk();
        $this->put(route('workers.update', $worker), array_replace($payload, ['nombres' => 'Nombre autorizado']))->assertRedirect();
        $this->patch(route('workers.status', $worker), ['is_active' => 0])->assertForbidden();
        $this->put(route('workers.update', $worker), array_replace($payload, ['is_active' => 0]))->assertForbidden();
        $this->assertTrue($worker->fresh()->is_active);
        $this->assertSame('Nombre autorizado', $worker->fresh()->nombres);
    }

    public function test_user_edit_permission_does_not_allow_role_status_or_password_changes(): void
    {
        $role = $this->role(['panel.view', 'users.view', 'users.update']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $target = User::factory()->create(['role_id' => $this->role(['panel.view'])->id]);
        $payload = ['name' => 'Nombre autorizado', 'email' => $target->email, 'role_id' => $target->role_id, 'is_active' => 1];
        $this->signIn($actor)->get(route('users.index'))->assertOk()->assertDontSee('Nuevo usuario')->assertDontSee('Desactivar');
        $this->get(route('users.edit', $target))->assertOk()->assertDontSee('name="password"', false);
        $this->put(route('users.update', $target), $payload)->assertRedirect();
        $this->put(route('users.update', $target), array_replace($payload, ['role_id' => 3]))->assertForbidden();
        $this->put(route('users.update', $target), array_replace($payload, ['is_active' => 0]))->assertForbidden();
        $this->put(route('users.update', $target), array_replace($payload, ['password' => 'ClaveSegura2026!', 'password_confirmation' => 'ClaveSegura2026!']))->assertForbidden();
        $this->patch(route('users.status', $target), ['is_active' => 0])->assertForbidden();
        $this->assertSame('Nombre autorizado', $target->fresh()->name);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame(1, $target->fresh()->session_version);
    }

    public function test_partial_admin_cannot_grant_or_assign_permissions_it_does_not_have(): void
    {
        $role = $this->role(['panel.view', 'roles.view', 'roles.create', 'roles.update', 'roles.assign_permissions', 'users.view', 'users.update', 'users.create', 'users.assign_role', 'users.change_status', 'users.change_password']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $targetRole = $this->role(['panel.view']);
        $target = User::factory()->create(['role_id' => $targetRole->id]);
        $higher = $this->admin();
        $this->signIn($actor);
        $this->put(route('roles.update', $targetRole), ['permissions' => $this->ids(['workers.create'])])->assertForbidden();
        $this->post(route('roles.store'), ['code' => 'escalada', 'name' => 'Escalada', 'is_active' => 1, 'permissions' => $this->ids(['workers.create'])])->assertForbidden();
        $this->put(route('users.update', $target), ['name' => $target->name, 'email' => $target->email, 'is_active' => 1, 'role_id' => 1])->assertForbidden();
        $this->get(route('users.edit', $higher))->assertForbidden();
        $this->patch(route('users.status', $higher), ['is_active' => 0])->assertForbidden();
        $this->get(route('roles.edit', $higher->role))->assertForbidden();
        $this->assertDatabaseMissing('roles', ['code' => 'escalada']);
        $this->assertSame(['panel.view'], $targetRole->fresh()->assignedPermissionCodes());
        $this->assertSame($targetRole->id, $target->fresh()->role_id);
    }

    public function test_role_editing_does_not_implicitly_allow_permission_or_status_changes(): void
    {
        $role = $this->role(['panel.view', 'roles.view', 'roles.update']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $target = $this->role(['panel.view']);
        $this->signIn($actor)->put(route('roles.update', $target), ['name' => 'Nombre válido'])->assertRedirect();
        $this->put(route('roles.update', $target), ['permissions' => []])->assertForbidden();
        $this->put(route('roles.update', $target), ['is_active' => 0])->assertForbidden();
        $this->patch(route('roles.status', $target), ['is_active' => 0])->assertForbidden();
        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame(['panel.view'], $target->fresh()->assignedPermissionCodes());
    }

    public function test_revocation_invalidates_all_previous_role_sessions_and_fresh_login_uses_new_permissions(): void
    {
        $role = $this->role(['panel.view', 'workers.view', 'workers.create']);
        $first = User::factory()->create(['role_id' => $role->id, 'password' => 'ClaveSegura2026!']);
        $second = User::factory()->create(['role_id' => $role->id]);
        $oldVersion = $role->permissions_version;
        // The instances intentionally keep their previously loaded role.
        $first->load('role');
        $second->load('role');
        app(SaveRole::class)->handle($this->admin(), $role, ['permissions' => $this->ids(['panel.view', 'workers.view'])]);
        foreach ([$first, $second] as $user) {
            $this->actingAs($user)->withSession(['auth_version' => 1, 'auth_role_id' => $role->id, 'auth_role_version' => $oldVersion])->getJson(route('workers.create'))->assertUnauthorized();
            $this->assertGuest();
        }
        $this->post(route('login.store'), ['email' => $first->email, 'password' => 'ClaveSegura2026!'])->assertRedirect(route('dashboard'));
        $this->get(route('workers.index'))->assertOk();
        $this->get(route('workers.create'))->assertForbidden();
    }

    public function test_grants_also_invalidate_old_sessions_and_missing_session_version_is_denied(): void
    {
        $role = $this->role(['panel.view']);
        $user = User::factory()->create(['role_id' => $role->id]);
        $this->signIn($user);
        app(SaveRole::class)->handle($this->admin(), $role, ['permissions' => $this->ids(['panel.view', 'workers.view'])]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs($user->fresh())->withSession(['auth_version' => 1, 'auth_role_id' => $role->id, 'auth_role_version' => null])->getJson(route('dashboard'))->assertUnauthorized();
        $this->signIn($user->fresh())->get(route('workers.index'))->assertOk();
    }

    public function test_inactive_roles_cannot_login_and_old_sessions_do_not_return_after_reactivation(): void
    {
        $role = $this->role(['panel.view']);
        $user = User::factory()->create(['role_id' => $role->id, 'password' => 'ClaveSegura2026!']);
        $admin = $this->admin();
        app(SaveRole::class)->handle($admin, $role, ['is_active' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'ClaveSegura2026!'])->assertSessionHasErrors('email');
        $this->assertGuest();
        app(SaveRole::class)->handle($admin, $role, ['is_active' => true]);
        $this->actingAs($user)->withSession(['auth_version' => 1, 'auth_role_id' => $role->id, 'auth_role_version' => 1])->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_permission_change_mid_request_is_rechecked_under_lock(): void
    {
        $role = $this->role(['panel.view', 'roles.update', 'users.update', 'users.change_status']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $actor->load('role');
        $target = User::factory()->create(['role_id' => $this->role(['panel.view'])->id]);
        $targetRole = $this->role(['panel.view']);
        app(SaveRole::class)->handle($this->admin(), $role, ['permissions' => $this->ids(['panel.view'])]);
        foreach ([fn () => app(UpdateUser::class)->handle($actor, $target, ['is_active' => false]), fn () => app(SaveRole::class)->handle($actor, $targetRole, ['name' => 'Cambio prohibido'])] as $operation) {
            try {
                $operation();
                $this->fail('Una autorización revocada no permite escribir.');
            } catch (AuthorizationException) {
                $this->assertTrue($target->fresh()->is_active);
            }
        }
        $this->assertSame('Delegado', $targetRole->fresh()->name);
    }

    public function test_worker_mutations_recheck_revocation_during_request(): void
    {
        $role = $this->role(['panel.view', 'workers.view', 'workers.create', 'workers.update', 'workers.change_status']);
        $actor = User::factory()->create(['role_id' => $role->id]);
        $actor->load('role');
        $worker = Worker::factory()->create();
        app(SaveRole::class)->handle($this->admin(), $role, ['permissions' => $this->ids(['panel.view', 'workers.view'])]);
        try {
            app(SaveWorker::class)->handle($actor, $worker, ['is_active' => false], statusOnly: true);
            $this->fail('Un permiso revocado no puede modificar trabajadores.');
        } catch (AuthorizationException) {
            $this->assertTrue($worker->fresh()->is_active);
        }
    }
}
