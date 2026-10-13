<?php

namespace Tests\Feature;

use App\Actions\Roles\SaveRole;
use App\Models\AdministrativeAuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdministrativeAudit;
use App\Services\SecurityMutation;
use App\Support\PermissionCatalog;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    private function admin(): User
    {
        return User::factory()->create(['role_id' => Role::where('code', Role::ADMINISTRATOR)->value('id')]);
    }

    private function ids(array $codes): array
    {
        return Permission::whereIn('code', $codes)->pluck('id')->all();
    }

    private function custom(array $codes = []): Role
    {
        return app(SaveRole::class)->handle($this->admin(), null, ['code' => 'custom_'.bin2hex(random_bytes(4)), 'name' => 'Rol configurable', 'is_active' => true, 'permissions' => $this->ids($codes)]);
    }

    public function test_role_creation_editing_and_author_are_audited(): void
    {
        $admin = $this->admin();
        $this->signIn($admin)->get(route('roles.create'))->assertOk()->assertSee('Permisos del rol');
        $this->post(route('roles.store'), ['code' => ' SUPERVISOR ', 'name' => 'Supervisor', 'description' => 'Consulta interna', 'is_active' => 1, 'permissions' => $this->ids(['panel.view', 'workers.view']), 'created_by' => 999, 'permissions_version' => 999])->assertRedirect(route('roles.index'));
        $role = Role::where('code', 'supervisor')->firstOrFail();
        $this->assertSame($admin->id, $role->created_by);
        $this->assertSame(1, $role->permissions_version);
        $this->get(route('roles.edit', $role))->assertOk()->assertSee('Consulta interna');
        $this->put(route('roles.update', $role), ['name' => 'Supervisor actualizado', 'description' => 'Nueva descripción'])->assertRedirect(route('roles.index'));
        $this->assertSame('Supervisor actualizado', $role->fresh()->name);
        $this->assertSame(1, $role->fresh()->permissions_version);
        $this->assertCount(2, $role->fresh()->assignedPermissionCodes());
        $log = AdministrativeAuditLog::where('role_id', $role->id)->latest('id')->firstOrFail();
        $this->assertSame('Supervisor', $log->before_data['name']);
        $this->assertSame('Supervisor actualizado', $log->after_data['name']);
        $this->assertSame($admin->id, $log->actor_id);
    }

    public function test_permissions_are_granted_revoked_and_links_preserved(): void
    {
        $role = $this->custom(['panel.view', 'workers.view']);
        $this->signIn($this->admin());
        $this->put(route('roles.update', $role), ['permissions' => $this->ids(['panel.view', 'workers.view', 'workers.create'])])->assertRedirect();
        $this->assertSame(2, $role->fresh()->permissions_version);
        $this->put(route('roles.update', $role), ['permissions' => $this->ids(['panel.view'])])->assertRedirect();
        $this->assertSame(['panel.view'], $role->fresh()->assignedPermissionCodes());
        $this->assertSame(3, $role->fresh()->permissions_version);
        $this->assertDatabaseHas('permission_role', ['role_id' => $role->id, 'permission_id' => $this->ids(['workers.create'])[0], 'is_active' => false]);
        $log = AdministrativeAuditLog::where('role_id', $role->id)->latest('id')->firstOrFail();
        $this->assertSame(['workers.create', 'workers.view'], $log->after_data['revoked_permissions']);
        $this->put(route('roles.update', $role), ['permissions_selection' => 1])->assertRedirect();
        $this->assertSame([], $role->fresh()->assignedPermissionCodes());
        $this->put(route('roles.update', $role), ['permissions' => $this->ids(['workers.create'])])->assertRedirect();
        $this->assertDatabaseCount('permission_role', 28); // Initial 25 links plus three preserved custom links.
    }

    public function test_invalid_roles_and_permissions_have_spanish_messages(): void
    {
        $this->signIn($this->admin());
        foreach ([['code' => 'bad-code'], ['code' => 'administrador'], ['name' => ''], ['permissions' => [999999]], ['permissions' => [$this->ids(['panel.view'])[0], $this->ids(['panel.view'])[0]]], ['permissions' => 'wrong'], ['is_active' => 'wrong']] as $changes) {
            $this->post(route('roles.store'), array_replace(['code' => 'valid_code', 'name' => 'Nombre válido', 'is_active' => 1], $changes))->assertSessionHasErrors();
        }
        $role = $this->custom();
        $this->put(route('roles.update', $role), ['code' => 'changed'])->assertSessionHasErrors(['code' => 'No se permite modificar el campo código.']);
        $this->get(route('roles.index', ['status' => 'invalid']))->assertSessionHasErrors(['status' => 'Selecciona un valor válido para estado.']);
    }

    public function test_role_status_and_search_preserve_accounts(): void
    {
        $role = $this->custom(['panel.view']);
        $target = User::factory()->create(['role_id' => $role->id]);
        $this->signIn($this->admin());
        $this->patch(route('roles.status', $role), ['is_active' => 0])->assertRedirect();
        $this->assertFalse($role->fresh()->is_active);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'role_id' => $role->id]);
        $this->get(route('roles.index', ['q' => $role->code, 'status' => 'inactive']))->assertOk()->assertSee($role->name);
        $this->patch(route('roles.status', $role), ['is_active' => 1])->assertRedirect();
        $this->assertSame(3, $role->fresh()->permissions_version);
        $this->delete(route('roles.update', $role))->assertStatus(405);
    }

    public function test_last_full_administrative_role_cannot_be_disabled_or_lose_any_core_permission(): void
    {
        $admin = $this->admin();
        $this->signIn($admin);
        $before = AdministrativeAuditLog::count();
        $this->patch(route('roles.status', $admin->role), ['is_active' => 0])->assertSessionHasErrors('is_active');
        foreach (PermissionCatalog::ADMINISTRATIVE_ACCESS as $code) {
            $codes = array_values(array_diff(array_keys(PermissionCatalog::LABELS), [$code]));
            $this->put(route('roles.update', $admin->role), ['permissions' => $this->ids($codes)])->assertSessionHasErrors('is_active');
        }
        $this->assertTrue($admin->role->fresh()->is_active);
        $this->assertSame(1, $admin->role->fresh()->permissions_version);
        $this->assertSame($before, AdministrativeAuditLog::count());
        $this->assertCount(17, $admin->role->fresh()->assignedPermissionCodes());
    }

    public function test_two_administrators_in_same_role_do_not_bypass_last_role_guard(): void
    {
        $admin = $this->admin();
        $this->admin();
        $this->signIn($admin)->put(route('roles.update', $admin->role), ['permissions' => []])->assertSessionHasErrors('is_active');
        $this->assertTrue(app(SecurityMutation::class)->hasAdministrativeAccess());
    }

    public function test_full_access_in_custom_role_counts_as_administrator_and_can_take_over(): void
    {
        $role = $this->custom(array_keys(PermissionCatalog::LABELS));
        $customAdmin = User::factory()->create(['role_id' => $role->id]);
        $this->signIn($customAdmin)->patch(route('roles.status', Role::where('code', Role::ADMINISTRATOR)->firstOrFail()), ['is_active' => 0])->assertRedirect();
        $this->assertTrue(app(SecurityMutation::class)->hasAdministrativeAccess());
        $this->get(route('users.index'))->assertOk();
        $this->patch(route('users.status', $customAdmin), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->artisan('sga:create-admin')->assertFailed();
    }

    public function test_user_can_be_assigned_a_custom_role_but_cannot_be_activated_under_inactive_role(): void
    {
        $role = $this->custom(['panel.view', 'workers.view']);
        $admin = $this->admin();
        $target = User::factory()->create(['role_id' => 3]);
        $this->signIn($admin)->put(route('users.update', $target), ['name' => $target->name, 'email' => $target->email, 'is_active' => 1, 'role_id' => $role->id])->assertRedirect();
        $this->assertSame($role->id, $target->fresh()->role_id);
        $this->patch(route('users.status', $target), ['is_active' => 0])->assertRedirect();
        $this->patch(route('roles.status', $role), ['is_active' => 0])->assertRedirect();
        $this->patch(route('users.status', $target), ['is_active' => 1])->assertSessionHasErrors('role_id');
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_role_names_have_no_authorization_effect(): void
    {
        $role = $this->custom(['panel.view', 'workers.view']);
        Role::whereKey($role->id)->update(['name' => 'Administrador']);
        $this->signIn(User::factory()->create(['role_id' => $role->id]))->get(route('users.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('workers.index'))->assertOk();
        $this->post(route('roles.store'), ['code' => 'intruso', 'name' => 'Intruso', 'is_active' => 1])->assertForbidden();
    }

    public function test_unprivileged_roles_cannot_use_any_role_mutation_endpoint(): void
    {
        $role = Role::where('code', Role::WAREHOUSE_MANAGER)->firstOrFail();
        $this->signIn(User::factory()->create(['role_id' => $role->id]));
        $this->get(route('roles.create'))->assertForbidden();
        $this->get(route('roles.edit', $role))->assertForbidden();
        $this->put(route('roles.update', $role), ['name' => 'Intruso'])->assertForbidden();
        $this->patch(route('roles.status', $role), ['is_active' => 0])->assertForbidden();
        $this->assertSame('Encargado de almacén', $role->fresh()->name);
    }

    public function test_audit_failure_rolls_back_the_administrative_change(): void
    {
        $admin = $this->admin();
        $role = $this->custom(['panel.view']);
        $original = $role->name;
        $this->partialMock(AdministrativeAudit::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Fallo de auditoría simulado'));
        try {
            app(SaveRole::class)->handle($admin, $role, ['name' => 'Cambio sin auditoría']);
            $this->fail('La transacción debe abortar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
            $this->assertSame($original, $role->fresh()->name);
        }
    }
}
