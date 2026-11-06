<?php

namespace Tests\Feature;

use App\Actions\Usuarios\ActualizarUsuario;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function account(string $codigo = Rol::ADMINISTRATOR): Usuario
    {
        return Usuario::factory()->create(['rol_id' => Rol::where('codigo', $codigo)->value('id')]);
    }

    private function detalle(array $changes = []): array
    {
        return array_replace(['nombre' => 'Operador de almacén', 'correo' => 'operador@example.com', 'contrasena' => 'ClaveSegura2026!', 'contrasena_confirmation' => 'ClaveSegura2026!', 'rol_id' => Rol::where('codigo', Rol::WAREHOUSE_MANAGER)->value('id'), 'activo' => '1'], $changes);
    }

    public function test_administrator_can_create_users_and_password_is_hashed(): void
    {
        $this->signIn($this->account());
        $this->get(route('usuarios.create'))->assertOk();
        $this->post(route('usuarios.store'), $this->detalle(['correo' => ' OPERADOR@EXAMPLE.COM ', 'version_sesion' => 999]))->assertRedirect(route('usuarios.index'));
        $usuario = Usuario::where('correo', 'operador@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('ClaveSegura2026!', $usuario->contrasena));
        $this->assertSame(1, $usuario->version_sesion);
        $this->assertArrayNotHasKey('contrasena', $usuario->toArray());
        $this->get(route('usuarios.index'))->assertOk()->assertSee('Operador de almacén')->assertSee('Encargado de almacén');
    }

    public function test_non_administrators_cannot_access_any_user_operation(): void
    {
        $target = $this->account(Rol::AUDITOR);
        foreach ([Rol::WAREHOUSE_MANAGER, Rol::AUDITOR] as $rol) {
            $this->signIn($this->account($rol));
            $this->get(route('usuarios.index'))->assertForbidden();
            $this->get(route('usuarios.create'))->assertForbidden();
            $this->get(route('usuarios.edit', $target))->assertForbidden();
            $this->post(route('usuarios.store'), $this->detalle())->assertForbidden();
            $this->put(route('usuarios.update', $target), $this->detalle())->assertForbidden();
            $this->patch(route('usuarios.estado', $target), ['activo' => 0])->assertForbidden();
        }
        $this->assertSame(Rol::AUDITOR, $target->fresh()->rol->codigo);
        $this->assertTrue($target->fresh()->activo);
    }

    public function test_validation_rejects_duplicate_email_unknown_roles_and_weak_passwords(): void
    {
        $admin = $this->account();
        $this->signIn($admin);
        $unexpected = Rol::create(['codigo' => 'unexpected', 'nombre' => 'No permitido', 'activo' => false]);
        foreach ([['correo' => strtoupper($admin->correo)], ['rol_id' => 999999], ['rol_id' => $unexpected->id], ['contrasena' => 'weak', 'contrasena_confirmation' => 'weak'], ['contrasena_confirmation' => 'different'], ['activo' => 'invalid'], ['nombre' => '']] as $changes) {
            $this->post(route('usuarios.store'), $this->detalle($changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_update_preserves_blank_password_and_revokes_access_when_role_changes(): void
    {
        $admin = $this->account();
        $target = $this->account(Rol::AUDITOR);
        $contrasena = $target->contrasena;
        $this->signIn($admin)->get(route('usuarios.edit', $target))->assertOk();
        $this->put(route('usuarios.update', $target), $this->detalle(['correo' => $target->correo, 'contrasena' => '', 'contrasena_confirmation' => '']))->assertRedirect(route('usuarios.index'));
        $this->assertSame($contrasena, $target->fresh()->contrasena);
        $this->assertSame(Rol::WAREHOUSE_MANAGER, $target->fresh()->rol->codigo);
        $this->assertSame(2, $target->fresh()->version_sesion);
        $this->actingAs($target->fresh())->withSession(['auth_version' => 1])->getJson('/panel')->assertUnauthorized();
    }

    public function test_password_change_invalidates_previous_password_and_sessions(): void
    {
        $this->signIn($this->account());
        $target = $this->account(Rol::WAREHOUSE_MANAGER);
        $this->put(route('usuarios.update', $target), $this->detalle(['correo' => $target->correo]))->assertRedirect(route('usuarios.index'));
        $this->assertTrue(Hash::check('ClaveSegura2026!', $target->fresh()->contrasena));
        $this->assertFalse(Hash::check('contrasena', $target->fresh()->contrasena));
        $this->assertSame(2, $target->fresh()->version_sesion);
    }

    public function test_accounts_are_deactivated_and_reactivated_without_deleting_them(): void
    {
        $this->signIn($this->account());
        $target = $this->account(Rol::WAREHOUSE_MANAGER);
        $this->patch(route('usuarios.estado', $target), ['activo' => 0, 'rol_id' => Rol::where('codigo', Rol::ADMINISTRATOR)->value('id')])->assertRedirect(route('usuarios.index'));
        $this->assertFalse($target->fresh()->activo);
        $this->assertSame(Rol::WAREHOUSE_MANAGER, $target->fresh()->rol->codigo);
        $this->assertSame(2, $target->fresh()->version_sesion);
        $this->patch(route('usuarios.estado', $target), ['activo' => 1])->assertRedirect(route('usuarios.index'));
        $this->assertTrue($target->fresh()->activo);
        $this->assertSame(3, $target->fresh()->version_sesion);
        $this->assertDatabaseCount('usuarios', 2);
    }

    public function test_last_active_administrator_cannot_be_disabled_or_demoted(): void
    {
        $admin = $this->account();
        $this->account()->forceFill(['activo' => false])->save();
        $this->signIn($admin);
        $this->patch(route('usuarios.estado', $admin), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->put(route('usuarios.update', $admin), $this->detalle(['correo' => $admin->correo]))->assertSessionHasErrors('activo');
        $this->assertTrue($admin->fresh()->activo);
        $this->assertSame(Rol::ADMINISTRATOR, $admin->fresh()->rol->codigo);
        $this->assertSame(1, $admin->fresh()->version_sesion);
    }

    public function test_an_administrator_can_be_disabled_when_another_remains_active(): void
    {
        $this->signIn($this->account());
        $target = $this->account();
        $this->patch(route('usuarios.estado', $target), ['activo' => 0])->assertRedirect(route('usuarios.index'));
        $this->assertFalse($target->fresh()->activo);
    }

    public function test_user_list_filters_and_paginates_without_losing_filters(): void
    {
        $this->signIn($this->account());
        Usuario::factory()->count(22)->create(['nombre' => 'Coincidencia', 'activo' => false]);
        $this->get(route('usuarios.index', ['q' => 'Coincidencia', 'estado' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('estado=inactivo', false);
        $this->get(route('usuarios.index', ['q' => 'Coincidencia', 'estado' => 'active']))->assertOk()->assertSee('No se encontraron usuarios.');
        $this->get(route('usuarios.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
    }

    public function test_user_names_are_escaped_and_delete_route_is_absent(): void
    {
        $this->signIn($this->account());
        $target = $this->account(Rol::AUDITOR);
        $target->update(['nombre' => '<script>alert(1)</script>']);
        $this->get(route('usuarios.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->delete('/usuarios/'.$target->id)->assertStatus(405);
        $this->assertDatabaseHas('usuarios', ['id' => $target->id]);
    }

    public function test_bootstrap_command_creates_an_administrator_and_refuses_a_second_run(): void
    {
        $this->artisan('sga:create-admin')
            ->expectsQuestion('Nombre completo', 'Administrador inicial')
            ->expectsQuestion('Correo electrónico', ' ADMIN@EXAMPLE.COM ')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número)', 'ClaveSegura2026!')
            ->expectsQuestion('Confirma la contraseña', 'ClaveSegura2026!')
            ->expectsOutput('Administrador creado.')->assertSuccessful();
        $usuario = Usuario::where('correo', 'admin@example.com')->firstOrFail();
        $this->assertSame(Rol::ADMINISTRATOR, $usuario->rol->codigo);
        $this->assertTrue(Hash::check('ClaveSegura2026!', $usuario->contrasena));
        $this->artisan('sga:create-admin')->expectsOutput('Ya existe un administrador activo. Gestiona nuevas cuentas desde el sistema.')->assertFailed();
        $this->assertDatabaseCount('usuarios', 1);
    }

    public function test_bootstrap_command_rejects_weak_credentials(): void
    {
        $this->artisan('sga:create-admin')
            ->expectsQuestion('Nombre completo', 'Administrador')
            ->expectsQuestion('Correo electrónico', 'admin@example.com')
            ->expectsQuestion('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número)', 'weak')
            ->expectsQuestion('Confirma la contraseña', 'weak')->assertFailed();
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_permission_changes_during_a_request_are_rechecked_before_mutation(): void
    {
        $operator = $this->account();
        $this->account();
        $target = $this->account(Rol::WAREHOUSE_MANAGER);
        // Keep the request's old identity while another operation revokes it.
        Usuario::whereKey($operator->id)->update(['rol_id' => Rol::where('codigo', Rol::AUDITOR)->value('id'), 'version_sesion' => 2]);
        try {
            app(ActualizarUsuario::class)->handle($operator, $target, ['activo' => false]);
            $this->fail('Una autorización anterior no debe permitir modificar usuarios.');
        } catch (AuthorizationException) {
            $this->assertTrue($target->fresh()->activo);
        }
    }

    public function test_name_only_changes_preserve_access_and_duplicate_updates_are_rejected(): void
    {
        $operator = $this->account();
        $target = $this->account(Rol::WAREHOUSE_MANAGER);
        $this->signIn($operator);
        $this->put(route('usuarios.update', $target), $this->detalle(['nombre' => 'Nombre actualizado', 'correo' => $target->correo, 'contrasena' => '', 'contrasena_confirmation' => '']))->assertRedirect(route('usuarios.index'));
        $this->assertSame(1, $target->fresh()->version_sesion);
        $this->put(route('usuarios.update', $target), $this->detalle(['correo' => $operator->correo]))->assertSessionHasErrors('correo');
        $this->assertSame($target->correo, $target->fresh()->correo);
    }
}
