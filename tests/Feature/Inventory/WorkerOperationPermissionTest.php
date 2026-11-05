<?php

namespace Tests\Feature\Inventory;

use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\PermisosOperacionTrabajador;
use Illuminate\Support\Facades\DB;
use Tests\Support\CargadorMigracionArchivada;
use Tests\TestCase;

class WorkerOperationPermissionTest extends TestCase
{
    public function test_defaults_and_repeat_migration_preserve_revocations_unknown_roles_and_people(): void
    {
        $this->assertDatabaseCount('permisos', 123);
        $this->assertDatabaseCount('permiso_rol', 260);
        foreach ([1 => 123, 2 => 96, 3 => 41] as $id => $count) {
            $this->assertCount($count, Rol::findOrFail($id)->codigosPermisosAsignados());
        }
        foreach (array_keys(PermisosOperacionTrabajador::LABELS) as $codigo) {
            $this->assertSame(str_ends_with($codigo, '.view'), in_array($codigo, Rol::findOrFail(3)->codigosPermisosAsignados(), true));
        }
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $manager = Rol::findOrFail(2);
        $codes = array_diff($manager->codigosPermisosAsignados(), ['loans.confirm']);
        app(GuardarRol::class)->handle($admin, $manager, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $unknown = Rol::create(['codigo' => 'unknown_s4', 'nombre' => 'Administrador']);
        $usuario = Usuario::factory()->create(['rol_id' => $unknown->id]);
        $before = $usuario->fresh()->getAttributes();
        $versions = Rol::pluck('version_permisos', 'id')->all();
        $links = DB::table('permiso_rol')->orderBy('id')->get()->toJson();
        $logs = DB::table('registros_auditoria_administrativa')->count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_19_000008_add_worker_operation_permissions.php'));
        $migration->up();
        $migration->up();
        $this->assertSame($links, DB::table('permiso_rol')->orderBy('id')->get()->toJson());
        $this->assertSame($versions, Rol::pluck('version_permisos', 'id')->all());
        $this->assertSame($logs, DB::table('registros_auditoria_administrativa')->count());
        $this->assertSame([], $unknown->codigosPermisosAsignados());
        $this->assertSame($before, $usuario->fresh()->getAttributes());
    }

    public function test_revocation_invalidates_worker_operation_session(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $manager = Usuario::factory()->create(['rol_id' => 2]);
        $this->signIn($manager)->get(route('operaciones-trabajadores.create', 'loans'))->assertOk();
        $codes = array_diff($manager->rol->codigosPermisosAsignados(), ['loans.confirm']);
        app(GuardarRol::class)->handle($admin, $manager->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->postJson(route('operaciones-trabajadores.confirm', ['loans', 1]), ['version_bloqueo' => 1])->assertUnauthorized();
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
