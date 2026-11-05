<?php

namespace Tests\Feature;

use App\Actions\Roles\GuardarRol;
use App\Actions\Trabajadores\GuardarTrabajador;
use App\Actions\Usuarios\ActualizarUsuario;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class PermissionAuthorizationTest extends TestCase
{
    private function admin(): Usuario
    {
        return Usuario::factory()->create(['rol_id' => 1]);
    }

    private function rol(array $codes): Rol
    {
        return app(GuardarRol::class)->handle($this->admin(), null, ['codigo' => 'delegado_'.bin2hex(random_bytes(4)), 'nombre' => 'Delegado', 'activo' => true, 'permisos' => $this->ids($codes)]);
    }

    private function ids(array $codes): array
    {
        return Permiso::whereIn('codigo', $codes)->pluck('id')->all();
    }

    public function test_worker_permissions_are_independent_for_read_create_edit_and_status(): void
    {
        $rol = $this->rol(['panel.view', 'workers.view', 'workers.update']);
        $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
        $trabajador = Trabajador::factory()->create();
        $detalle = $trabajador->only(['codigo', 'nombres', 'apellidos', 'documento_identidad', 'cargo', 'area', 'activo']);
        $this->signIn($usuario)->get(route('trabajadores.index'))->assertOk()->assertDontSee('Nuevo trabajador')->assertDontSee('Desactivar');
        $this->get(route('trabajadores.create'))->assertForbidden();
        $this->post(route('trabajadores.store'), $detalle)->assertForbidden();
        $this->get(route('trabajadores.edit', $trabajador))->assertOk();
        $this->put(route('trabajadores.update', $trabajador), array_replace($detalle, ['nombres' => 'Nombre autorizado']))->assertRedirect();
        $this->patch(route('trabajadores.estado', $trabajador), ['activo' => 0])->assertForbidden();
        $this->put(route('trabajadores.update', $trabajador), array_replace($detalle, ['activo' => 0]))->assertForbidden();
        $this->assertTrue($trabajador->fresh()->activo);
        $this->assertSame('Nombre autorizado', $trabajador->fresh()->nombres);
    }

    public function test_user_edit_permission_does_not_allow_role_status_or_password_changes(): void
    {
        $rol = $this->rol(['panel.view', 'users.view', 'users.update']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $target = Usuario::factory()->create(['rol_id' => $this->rol(['panel.view'])->id]);
        $detalle = ['nombre' => 'Nombre autorizado', 'correo' => $target->correo, 'rol_id' => $target->rol_id, 'activo' => 1];
        $this->signIn($actor)->get(route('usuarios.index'))->assertOk()->assertDontSee('Nuevo usuario')->assertDontSee('Desactivar');
        $this->get(route('usuarios.edit', $target))->assertOk()->assertDontSee('name="contrasena"', false);
        $this->put(route('usuarios.update', $target), $detalle)->assertRedirect();
        $this->put(route('usuarios.update', $target), array_replace($detalle, ['rol_id' => 3]))->assertForbidden();
        $this->put(route('usuarios.update', $target), array_replace($detalle, ['activo' => 0]))->assertForbidden();
        $this->put(route('usuarios.update', $target), array_replace($detalle, ['contrasena' => 'ClaveSegura2026!', 'contrasena_confirmation' => 'ClaveSegura2026!']))->assertForbidden();
        $this->patch(route('usuarios.estado', $target), ['activo' => 0])->assertForbidden();
        $this->assertSame('Nombre autorizado', $target->fresh()->nombre);
        $this->assertTrue($target->fresh()->activo);
        $this->assertSame(1, $target->fresh()->version_sesion);
    }

    public function test_partial_admin_cannot_grant_or_assign_permissions_it_does_not_have(): void
    {
        $rol = $this->rol(['panel.view', 'roles.view', 'roles.create', 'roles.update', 'roles.assign_permissions', 'users.view', 'users.update', 'users.create', 'users.assign_role', 'users.change_status', 'users.change_password']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $targetRole = $this->rol(['panel.view']);
        $target = Usuario::factory()->create(['rol_id' => $targetRole->id]);
        $higher = $this->admin();
        $this->signIn($actor);
        $this->put(route('roles.update', $targetRole), ['permisos' => $this->ids(['workers.create'])])->assertForbidden();
        $this->post(route('roles.store'), ['codigo' => 'escalada', 'nombre' => 'Escalada', 'activo' => 1, 'permisos' => $this->ids(['workers.create'])])->assertForbidden();
        $this->put(route('usuarios.update', $target), ['nombre' => $target->nombre, 'correo' => $target->correo, 'activo' => 1, 'rol_id' => 1])->assertForbidden();
        $this->get(route('usuarios.edit', $higher))->assertForbidden();
        $this->patch(route('usuarios.estado', $higher), ['activo' => 0])->assertForbidden();
        $this->get(route('roles.edit', $higher->rol))->assertForbidden();
        $this->assertDatabaseMissing('roles', ['codigo' => 'escalada']);
        $this->assertSame(['panel.view'], $targetRole->fresh()->codigosPermisosAsignados());
        $this->assertSame($targetRole->id, $target->fresh()->rol_id);
    }

    public function test_role_editing_does_not_implicitly_allow_permission_or_status_changes(): void
    {
        $rol = $this->rol(['panel.view', 'roles.view', 'roles.update']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $target = $this->rol(['panel.view']);
        $this->signIn($actor)->put(route('roles.update', $target), ['nombre' => 'Nombre válido'])->assertRedirect();
        $this->put(route('roles.update', $target), ['permisos' => []])->assertForbidden();
        $this->put(route('roles.update', $target), ['activo' => 0])->assertForbidden();
        $this->patch(route('roles.estado', $target), ['activo' => 0])->assertForbidden();
        $this->assertTrue($target->fresh()->activo);
        $this->assertSame(['panel.view'], $target->fresh()->codigosPermisosAsignados());
    }

    public function test_revocation_invalidates_all_previous_role_sessions_and_fresh_login_uses_new_permissions(): void
    {
        $rol = $this->rol(['panel.view', 'workers.view', 'workers.create']);
        $first = Usuario::factory()->create(['rol_id' => $rol->id, 'contrasena' => 'ClaveSegura2026!']);
        $second = Usuario::factory()->create(['rol_id' => $rol->id]);
        $oldVersion = $rol->version_permisos;
        // The instances intentionally keep their previously loaded rol.
        $first->load('rol');
        $second->load('rol');
        app(GuardarRol::class)->handle($this->admin(), $rol, ['permisos' => $this->ids(['panel.view', 'workers.view'])]);
        foreach ([$first, $second] as $usuario) {
            $this->actingAs($usuario)->withSession(['auth_version' => 1, 'auth_role_id' => $rol->id, 'auth_role_version' => $oldVersion])->getJson(route('trabajadores.create'))->assertUnauthorized();
            $this->assertGuest();
        }
        $this->post(route('login.store'), ['correo' => $first->correo, 'contrasena' => 'ClaveSegura2026!'])->assertRedirect(route('dashboard'));
        $this->get(route('trabajadores.index'))->assertOk();
        $this->get(route('trabajadores.create'))->assertForbidden();
    }

    public function test_grants_also_invalidate_old_sessions_and_missing_session_version_is_denied(): void
    {
        $rol = $this->rol(['panel.view']);
        $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->signIn($usuario);
        app(GuardarRol::class)->handle($this->admin(), $rol, ['permisos' => $this->ids(['panel.view', 'workers.view'])]);
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->actingAs($usuario->fresh())->withSession(['auth_version' => 1, 'auth_role_id' => $rol->id, 'auth_role_version' => null])->getJson(route('dashboard'))->assertUnauthorized();
        $this->signIn($usuario->fresh())->get(route('trabajadores.index'))->assertOk();
    }

    public function test_inactive_roles_cannot_login_and_old_sessions_do_not_return_after_reactivation(): void
    {
        $rol = $this->rol(['panel.view']);
        $usuario = Usuario::factory()->create(['rol_id' => $rol->id, 'contrasena' => 'ClaveSegura2026!']);
        $admin = $this->admin();
        app(GuardarRol::class)->handle($admin, $rol, ['activo' => false]);
        $this->post(route('login.store'), ['correo' => $usuario->correo, 'contrasena' => 'ClaveSegura2026!'])->assertSessionHasErrors('correo');
        $this->assertGuest();
        app(GuardarRol::class)->handle($admin, $rol, ['activo' => true]);
        $this->actingAs($usuario)->withSession(['auth_version' => 1, 'auth_role_id' => $rol->id, 'auth_role_version' => 1])->getJson(route('dashboard'))->assertUnauthorized();
    }

    public function test_permission_change_mid_request_is_rechecked_under_lock(): void
    {
        $rol = $this->rol(['panel.view', 'roles.update', 'users.update', 'users.change_status']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $actor->load('rol');
        $target = Usuario::factory()->create(['rol_id' => $this->rol(['panel.view'])->id]);
        $targetRole = $this->rol(['panel.view']);
        app(GuardarRol::class)->handle($this->admin(), $rol, ['permisos' => $this->ids(['panel.view'])]);
        foreach ([fn () => app(ActualizarUsuario::class)->handle($actor, $target, ['activo' => false]), fn () => app(GuardarRol::class)->handle($actor, $targetRole, ['nombre' => 'Cambio prohibido'])] as $operation) {
            try {
                $operation();
                $this->fail('Una autorización revocada no permite escribir.');
            } catch (AuthorizationException) {
                $this->assertTrue($target->fresh()->activo);
            }
        }
        $this->assertSame('Delegado', $targetRole->fresh()->nombre);
    }

    public function test_worker_mutations_recheck_revocation_during_request(): void
    {
        $rol = $this->rol(['panel.view', 'workers.view', 'workers.create', 'workers.update', 'workers.change_status']);
        $actor = Usuario::factory()->create(['rol_id' => $rol->id]);
        $actor->load('rol');
        $trabajador = Trabajador::factory()->create();
        app(GuardarRol::class)->handle($this->admin(), $rol, ['permisos' => $this->ids(['panel.view', 'workers.view'])]);
        try {
            app(GuardarTrabajador::class)->handle($actor, $trabajador, ['activo' => false], statusOnly: true);
            $this->fail('Un permiso revocado no puede modificar trabajadores.');
        } catch (AuthorizationException) {
            $this->assertTrue($trabajador->fresh()->activo);
        }
    }
}
