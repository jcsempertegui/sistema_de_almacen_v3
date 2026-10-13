<?php

namespace Tests\Feature;

use App\Actions\Users\UpdateUser;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function account(string $code = Role::ADMINISTRATOR): User
    {
        return User::factory()->create(['role_id' => Role::where('code', $code)->value('id')]);
    }

    private function payload(array $changes = []): array
    {
        return array_replace(['name' => 'Operador de almacén', 'email' => 'operador@example.com', 'password' => 'ClaveSegura2026!', 'password_confirmation' => 'ClaveSegura2026!', 'role_id' => Role::where('code', Role::WAREHOUSE_MANAGER)->value('id'), 'is_active' => '1'], $changes);
    }

    public function test_administrator_can_create_users_and_password_is_hashed(): void
    {
        $this->signIn($this->account());
        $this->get(route('users.create'))->assertOk();
        $this->post(route('users.store'), $this->payload(['email' => ' OPERADOR@EXAMPLE.COM ', 'session_version' => 999]))->assertRedirect(route('users.index'));
        $user = User::where('email', 'operador@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('ClaveSegura2026!', $user->password));
        $this->assertSame(1, $user->session_version);
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->get(route('users.index'))->assertOk()->assertSee('Operador de almacén')->assertSee('Encargado de almacén');
    }

    public function test_non_administrators_cannot_access_any_user_operation(): void
    {
        $target = $this->account(Role::AUDITOR);
        foreach ([Role::WAREHOUSE_MANAGER, Role::AUDITOR] as $role) {
            $this->signIn($this->account($role));
            $this->get(route('users.index'))->assertForbidden();
            $this->get(route('users.create'))->assertForbidden();
            $this->get(route('users.edit', $target))->assertForbidden();
            $this->post(route('users.store'), $this->payload())->assertForbidden();
            $this->put(route('users.update', $target), $this->payload())->assertForbidden();
            $this->patch(route('users.status', $target), ['is_active' => 0])->assertForbidden();
        }
        $this->assertSame(Role::AUDITOR, $target->fresh()->role->code);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_validation_rejects_duplicate_email_unknown_roles_and_weak_passwords(): void
    {
        $admin = $this->account();
        $this->signIn($admin);
        $unexpected = Role::create(['code' => 'unexpected', 'name' => 'No permitido', 'is_active' => false]);
        foreach ([['email' => strtoupper($admin->email)], ['role_id' => 999999], ['role_id' => $unexpected->id], ['password' => 'weak', 'password_confirmation' => 'weak'], ['password_confirmation' => 'different'], ['is_active' => 'invalid'], ['name' => '']] as $changes) {
            $this->post(route('users.store'), $this->payload($changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_update_preserves_blank_password_and_revokes_access_when_role_changes(): void
    {
        $admin = $this->account();
        $target = $this->account(Role::AUDITOR);
        $password = $target->password;
        $this->signIn($admin)->get(route('users.edit', $target))->assertOk();
        $this->put(route('users.update', $target), $this->payload(['email' => $target->email, 'password' => '', 'password_confirmation' => '']))->assertRedirect(route('users.index'));
        $this->assertSame($password, $target->fresh()->password);
        $this->assertSame(Role::WAREHOUSE_MANAGER, $target->fresh()->role->code);
        $this->assertSame(2, $target->fresh()->session_version);
        $this->actingAs($target->fresh())->withSession(['auth_version' => 1])->getJson('/panel')->assertUnauthorized();
    }

    public function test_password_change_invalidates_previous_password_and_sessions(): void
    {
        $this->signIn($this->account());
        $target = $this->account(Role::WAREHOUSE_MANAGER);
        $this->put(route('users.update', $target), $this->payload(['email' => $target->email]))->assertRedirect(route('users.index'));
        $this->assertTrue(Hash::check('ClaveSegura2026!', $target->fresh()->password));
        $this->assertFalse(Hash::check('password', $target->fresh()->password));
        $this->assertSame(2, $target->fresh()->session_version);
    }

    public function test_accounts_are_deactivated_and_reactivated_without_deleting_them(): void
    {
        $this->signIn($this->account());
        $target = $this->account(Role::WAREHOUSE_MANAGER);
        $this->patch(route('users.status', $target), ['is_active' => 0, 'role_id' => Role::where('code', Role::ADMINISTRATOR)->value('id')])->assertRedirect(route('users.index'));
        $this->assertFalse($target->fresh()->is_active);
        $this->assertSame(Role::WAREHOUSE_MANAGER, $target->fresh()->role->code);
        $this->assertSame(2, $target->fresh()->session_version);
        $this->patch(route('users.status', $target), ['is_active' => 1])->assertRedirect(route('users.index'));
        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame(3, $target->fresh()->session_version);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_last_active_administrator_cannot_be_disabled_or_demoted(): void
    {
        $admin = $this->account();
        $this->account()->forceFill(['is_active' => false])->save();
        $this->signIn($admin);
        $this->patch(route('users.status', $admin), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put(route('users.update', $admin), $this->payload(['email' => $admin->email]))->assertSessionHasErrors('is_active');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(Role::ADMINISTRATOR, $admin->fresh()->role->code);
        $this->assertSame(1, $admin->fresh()->session_version);
    }

    public function test_an_administrator_can_be_disabled_when_another_remains_active(): void
    {
        $this->signIn($this->account());
        $target = $this->account();
        $this->patch(route('users.status', $target), ['is_active' => 0])->assertRedirect(route('users.index'));
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_user_list_filters_and_paginates_without_losing_filters(): void
    {
        $this->signIn($this->account());
        User::factory()->count(22)->create(['name' => 'Coincidencia', 'is_active' => false]);
        $this->get(route('users.index', ['q' => 'Coincidencia', 'status' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('status=inactive', false);
        $this->get(route('users.index', ['q' => 'Coincidencia', 'status' => 'active']))->assertOk()->assertSee('No se encontraron usuarios.');
        $this->get(route('users.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
    }

    public function test_user_names_are_escaped_and_delete_route_is_absent(): void
    {
        $this->signIn($this->account());
        $target = $this->account(Role::AUDITOR);
        $target->update(['name' => '<script>alert(1)</script>']);
        $this->get(route('users.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->delete('/usuarios/'.$target->id)->assertStatus(405);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_bootstrap_command_creates_an_administrator_and_refuses_a_second_run(): void
    {
        $this->artisan('sga:create-admin')
            ->expectsQuestion('Nombre completo', 'Administrador inicial')
            ->expectsQuestion('Correo electrónico', ' ADMIN@EXAMPLE.COM ')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número)', 'ClaveSegura2026!')
            ->expectsQuestion('Confirma la contraseña', 'ClaveSegura2026!')
            ->expectsOutput('Administrador creado.')->assertSuccessful();
        $user = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertSame(Role::ADMINISTRATOR, $user->role->code);
        $this->assertTrue(Hash::check('ClaveSegura2026!', $user->password));
        $this->artisan('sga:create-admin')->expectsOutput('Ya existe un administrador activo. Gestiona nuevas cuentas desde el sistema.')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_bootstrap_command_rejects_weak_credentials(): void
    {
        $this->artisan('sga:create-admin')
            ->expectsQuestion('Nombre completo', 'Administrador')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número)', 'weak')
            ->expectsQuestion('Confirma la contraseña', 'weak')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_permission_changes_during_a_request_are_rechecked_before_mutation(): void
    {
        $operator = $this->account();
        $this->account();
        $target = $this->account(Role::WAREHOUSE_MANAGER);
        // Keep the request's old identity while another operation revokes it.
        User::whereKey($operator->id)->update(['role_id' => Role::where('code', Role::AUDITOR)->value('id'), 'session_version' => 2]);
        try {
            app(UpdateUser::class)->handle($operator, $target, ['is_active' => false]);
            $this->fail('Una autorización anterior no debe permitir modificar usuarios.');
        } catch (AuthorizationException) {
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_name_only_changes_preserve_access_and_duplicate_updates_are_rejected(): void
    {
        $operator = $this->account();
        $target = $this->account(Role::WAREHOUSE_MANAGER);
        $this->signIn($operator);
        $this->put(route('users.update', $target), $this->payload(['name' => 'Nombre actualizado', 'email' => $target->email, 'password' => '', 'password_confirmation' => '']))->assertRedirect(route('users.index'));
        $this->assertSame(1, $target->fresh()->session_version);
        $this->put(route('users.update', $target), $this->payload(['email' => $operator->email]))->assertSessionHasErrors('email');
        $this->assertSame($target->email, $target->fresh()->email);
    }
}
