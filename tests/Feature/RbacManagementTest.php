<?php

namespace Tests\Feature;

use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaAdministrativa;
use App\Services\ModificacionSegura;
use App\Support\CatalogoPermisos;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RbacManagementTest extends TestCase
{
    private function admin(): Usuario
    {
        return Usuario::factory()->create(['rol_id' => Rol::where('codigo', Rol::ADMINISTRATOR)->value('id')]);
    }

    private function ids(array $codes): array
    {
        return Permiso::whereIn('codigo', $codes)->pluck('id')->all();
    }

    private function custom(array $codes = []): Rol
    {
        return app(GuardarRol::class)->handle($this->admin(), null, ['codigo' => 'custom_'.bin2hex(random_bytes(4)), 'nombre' => 'Rol configurable', 'activo' => true, 'permisos' => $this->ids($codes)]);
    }

    public function test_role_creation_editing_and_author_are_audited(): void
    {
        $admin = $this->admin();
        $this->signIn($admin)->get(route('roles.create'))->assertOk()->assertSee('Permisos del rol');
        $this->post(route('roles.store'), ['codigo' => ' SUPERVISOR ', 'nombre' => 'Supervisor', 'descripcion' => 'Consulta interna', 'activo' => 1, 'permisos' => $this->ids(['panel.view', 'workers.view']), 'creado_por' => 999, 'version_permisos' => 999])->assertRedirect(route('roles.index'));
        $rol = Rol::where('codigo', 'supervisor')->firstOrFail();
        $this->assertSame($admin->id, $rol->creado_por);
        $this->assertSame(1, $rol->version_permisos);
        $this->get(route('roles.edit', $rol))->assertOk()->assertSee('Consulta interna');
        $this->put(route('roles.update', $rol), ['nombre' => 'Supervisor actualizado', 'descripcion' => 'Nueva descripción'])->assertRedirect(route('roles.index'));
        $this->assertSame('Supervisor actualizado', $rol->fresh()->nombre);
        $this->assertSame(1, $rol->fresh()->version_permisos);
        $this->assertCount(2, $rol->fresh()->codigosPermisosAsignados());
        $log = RegistroAuditoriaAdministrativa::where('rol_id', $rol->id)->latest('id')->firstOrFail();
        $this->assertSame('Supervisor', $log->datos_anteriores['nombre']);
        $this->assertSame('Supervisor actualizado', $log->datos_posteriores['nombre']);
        $this->assertSame($admin->id, $log->actor_id);
    }

    public function test_permissions_are_granted_revoked_and_links_preserved(): void
    {
        $initialLinks = DB::table('permiso_rol')->count();
        $rol = $this->custom(['panel.view', 'workers.view']);
        $this->signIn($this->admin());
        $this->put(route('roles.update', $rol), ['permisos' => $this->ids(['panel.view', 'workers.view', 'workers.create'])])->assertRedirect();
        $this->assertSame(2, $rol->fresh()->version_permisos);
        $this->put(route('roles.update', $rol), ['permisos' => $this->ids(['panel.view'])])->assertRedirect();
        $this->assertSame(['panel.view'], $rol->fresh()->codigosPermisosAsignados());
        $this->assertSame(3, $rol->fresh()->version_permisos);
        $this->assertDatabaseHas('permiso_rol', ['rol_id' => $rol->id, 'permiso_id' => $this->ids(['workers.create'])[0], 'activo' => false]);
        $log = RegistroAuditoriaAdministrativa::where('rol_id', $rol->id)->latest('id')->firstOrFail();
        $this->assertSame(['workers.create', 'workers.view'], $log->datos_posteriores['revoked_permissions']);
        $this->put(route('roles.update', $rol), ['permissions_selection' => 1])->assertRedirect();
        $this->assertSame([], $rol->fresh()->codigosPermisosAsignados());
        $this->put(route('roles.update', $rol), ['permisos' => $this->ids(['workers.create'])])->assertRedirect();
        $this->assertDatabaseCount('permiso_rol', $initialLinks + 3);
    }

    public function test_invalid_roles_and_permissions_have_spanish_messages(): void
    {
        $this->signIn($this->admin());
        foreach ([['codigo' => 'bad-codigo'], ['codigo' => 'administrador'], ['nombre' => ''], ['permisos' => [999999]], ['permisos' => [$this->ids(['panel.view'])[0], $this->ids(['panel.view'])[0]]], ['permisos' => 'wrong'], ['activo' => 'wrong']] as $changes) {
            $this->post(route('roles.store'), array_replace(['codigo' => 'valid_code', 'nombre' => 'Nombre válido', 'activo' => 1], $changes))->assertSessionHasErrors();
        }
        $rol = $this->custom();
        $this->put(route('roles.update', $rol), ['codigo' => 'changed'])->assertSessionHasErrors(['codigo' => 'No se permite modificar el campo código.']);
        $this->get(route('roles.index', ['estado' => 'invalid']))->assertSessionHasErrors(['estado' => 'Selecciona un valor válido para estado.']);
    }

    public function test_role_status_and_search_preserve_accounts(): void
    {
        $rol = $this->custom(['panel.view']);
        $target = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->signIn($this->admin());
        $this->patch(route('roles.estado', $rol), ['activo' => 0])->assertRedirect();
        $this->assertFalse($rol->fresh()->activo);
        $this->assertDatabaseHas('usuarios', ['id' => $target->id, 'rol_id' => $rol->id]);
        $this->get(route('roles.index', ['q' => $rol->codigo, 'estado' => 'inactive']))->assertOk()->assertSee($rol->nombre);
        $this->patch(route('roles.estado', $rol), ['activo' => 1])->assertRedirect();
        $this->assertSame(3, $rol->fresh()->version_permisos);
        $this->delete(route('roles.update', $rol))->assertStatus(405);
    }

    public function test_last_full_administrative_role_cannot_be_disabled_or_lose_any_core_permission(): void
    {
        $admin = $this->admin();
        $version = $admin->rol->version_permisos;
        $this->signIn($admin);
        $before = RegistroAuditoriaAdministrativa::count();
        $this->patch(route('roles.estado', $admin->rol), ['activo' => 0])->assertSessionHasErrors('activo');
        foreach (CatalogoPermisos::ADMINISTRATIVE_ACCESS as $codigo) {
            $codes = array_values(array_diff(array_keys(CatalogoPermisos::LABELS), [$codigo]));
            $this->put(route('roles.update', $admin->rol), ['permisos' => $this->ids($codes)])->assertSessionHasErrors('activo');
        }
        $this->assertTrue($admin->rol->fresh()->activo);
        $this->assertSame($version, $admin->rol->fresh()->version_permisos);
        $this->assertSame($before, RegistroAuditoriaAdministrativa::count());
        $this->assertCount(count(CatalogoPermisos::LABELS), $admin->rol->fresh()->codigosPermisosAsignados());
    }

    public function test_two_administrators_in_same_role_do_not_bypass_last_role_guard(): void
    {
        $admin = $this->admin();
        $this->admin();
        $this->signIn($admin)->put(route('roles.update', $admin->rol), ['permisos' => []])->assertSessionHasErrors('activo');
        $this->assertTrue(app(ModificacionSegura::class)->hasAdministrativeAccess());
    }

    public function test_full_access_in_custom_role_counts_as_administrator_and_can_take_over(): void
    {
        $rol = $this->custom(array_keys(CatalogoPermisos::LABELS));
        $customAdmin = Usuario::factory()->create(['rol_id' => $rol->id]);
        $this->signIn($customAdmin)->patch(route('roles.estado', Rol::where('codigo', Rol::ADMINISTRATOR)->firstOrFail()), ['activo' => 0])->assertRedirect();
        $this->assertTrue(app(ModificacionSegura::class)->hasAdministrativeAccess());
        $this->get(route('usuarios.index'))->assertOk();
        $this->patch(route('usuarios.estado', $customAdmin), ['activo' => 0])->assertSessionHasErrors('activo');
        $this->artisan('sga:create-admin')->assertFailed();
    }

    public function test_user_can_be_assigned_a_custom_role_but_cannot_be_activated_under_inactive_role(): void
    {
        $rol = $this->custom(['panel.view', 'workers.view']);
        $admin = $this->admin();
        $target = Usuario::factory()->create(['rol_id' => 3]);
        $this->signIn($admin)->put(route('usuarios.update', $target), ['nombre' => $target->nombre, 'correo' => $target->correo, 'activo' => 1, 'rol_id' => $rol->id])->assertRedirect();
        $this->assertSame($rol->id, $target->fresh()->rol_id);
        $this->patch(route('usuarios.estado', $target), ['activo' => 0])->assertRedirect();
        $this->patch(route('roles.estado', $rol), ['activo' => 0])->assertRedirect();
        $this->patch(route('usuarios.estado', $target), ['activo' => 1])->assertSessionHasErrors('rol_id');
        $this->assertFalse($target->fresh()->activo);
    }

    public function test_role_names_have_no_authorization_effect(): void
    {
        $rol = $this->custom(['panel.view', 'workers.view']);
        Rol::whereKey($rol->id)->update(['nombre' => 'Administrador']);
        $this->signIn(Usuario::factory()->create(['rol_id' => $rol->id]))->get(route('usuarios.index'))->assertForbidden();
        $this->get(route('roles.index'))->assertForbidden();
        $this->get(route('trabajadores.index'))->assertOk();
        $this->post(route('roles.store'), ['codigo' => 'intruso', 'nombre' => 'Intruso', 'activo' => 1])->assertForbidden();
    }

    public function test_unprivileged_roles_cannot_use_any_role_mutation_endpoint(): void
    {
        $rol = Rol::where('codigo', Rol::WAREHOUSE_MANAGER)->firstOrFail();
        $this->signIn(Usuario::factory()->create(['rol_id' => $rol->id]));
        $this->get(route('roles.create'))->assertForbidden();
        $this->get(route('roles.edit', $rol))->assertForbidden();
        $this->put(route('roles.update', $rol), ['nombre' => 'Intruso'])->assertForbidden();
        $this->patch(route('roles.estado', $rol), ['activo' => 0])->assertForbidden();
        $this->assertSame('Encargado de almacén', $rol->fresh()->nombre);
    }

    public function test_audit_failure_rolls_back_the_administrative_change(): void
    {
        $admin = $this->admin();
        $rol = $this->custom(['panel.view']);
        $original = $rol->nombre;
        $this->partialMock(AuditoriaAdministrativa::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Fallo de auditoría simulado'));
        try {
            app(GuardarRol::class)->handle($admin, $rol, ['nombre' => 'Cambio sin auditoría']);
            $this->fail('La transacción debe abortar.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de auditoría simulado', $exception->getMessage());
            $this->assertSame($original, $rol->fresh()->nombre);
        }
    }
}
