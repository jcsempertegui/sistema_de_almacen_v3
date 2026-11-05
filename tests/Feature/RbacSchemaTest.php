<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Support\CatalogoPermisos;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CargadorMigracionArchivada;
use Tests\TestCase;

class RbacSchemaTest extends TestCase
{
    public function test_initial_catalog_and_role_ids_are_preserved(): void
    {
        $this->assertSame([1, 2, 3], Rol::whereIn('codigo', array_keys(Rol::LABELS))->orderBy('id')->pluck('id')->all());
        $this->assertDatabaseCount('permisos', count(CatalogoPermisos::LABELS));
        $this->assertCount(count(CatalogoPermisos::LABELS), Rol::where('codigo', Rol::ADMINISTRATOR)->firstOrFail()->codigosPermisosAsignados());
        $this->assertCount(96, Rol::where('codigo', Rol::WAREHOUSE_MANAGER)->firstOrFail()->codigosPermisosAsignados());
        $this->assertCount(41, Rol::where('codigo', Rol::AUDITOR)->firstOrFail()->codigosPermisosAsignados());
        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_initialization_preserves_unknown_roles_users_workers_and_revocations(): void
    {
        $rol = Rol::create(['codigo' => 'custom_without_grants', 'nombre' => 'Rol sin privilegios']);
        $usuario = Usuario::factory()->create(['rol_id' => $rol->id]);
        $trabajador = Trabajador::factory()->create();
        $trabajador->forceFill(['creado_por' => $usuario->id, 'modificado_por' => $usuario->id])->save();
        $userBefore = $usuario->fresh()->getAttributes();
        $workerBefore = $trabajador->fresh()->getAttributes();
        $admin = Rol::where('codigo', Rol::ADMINISTRATOR)->firstOrFail();
        $permiso = Permiso::where('codigo', 'workers.create')->firstOrFail();
        $admin->permisos()->updateExistingPivot($permiso->id, ['activo' => false]);
        $auditCount = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_12_000005_initialize_existing_role_permissions.php'));
        $migration->up();
        $this->seed(DatabaseSeeder::class);
        $this->assertSame([], $rol->codigosPermisosAsignados());
        $this->assertSame($userBefore, $usuario->fresh()->getAttributes());
        $this->assertSame($workerBefore, $trabajador->fresh()->getAttributes());
        $this->assertNotContains('workers.create', $admin->codigosPermisosAsignados());
        $this->assertSame($auditCount, RegistroAuditoriaAdministrativa::count());
    }

    public function test_duplicate_permission_codes_are_rejected_by_mariadb(): void
    {
        $this->expectException(QueryException::class);
        Permiso::create(['codigo' => 'panel.view', 'nombre' => 'Duplicado']);
    }

    public function test_duplicate_role_permission_links_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permiso_rol')->insert(['rol_id' => 1, 'permiso_id' => Permiso::where('codigo', 'panel.view')->value('id'), 'activo' => true]);
    }

    public function test_unknown_permission_foreign_keys_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permiso_rol')->insert(['rol_id' => 1, 'permiso_id' => 999999, 'activo' => true]);
    }

    public function test_audit_requires_exactly_one_existing_subject(): void
    {
        $this->expectException(QueryException::class);
        DB::table('registros_auditoria_administrativa')->insert(['accion' => 'invalid', 'origen' => 'test', 'datos_posteriores' => '{}']);
    }

    public function test_unknown_role_permission_foreign_keys_are_rejected(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permiso_rol')->insert(['rol_id' => 999999, 'permiso_id' => Permiso::firstOrFail()->id, 'activo' => true]);
    }

    public function test_permissions_with_history_cannot_be_deleted(): void
    {
        $this->expectException(QueryException::class);
        DB::table('permisos')->where('codigo', 'panel.view')->delete();
    }

    public function test_audit_rejects_unknown_subject_foreign_keys(): void
    {
        $this->expectException(QueryException::class);
        DB::table('registros_auditoria_administrativa')->insert(['rol_id' => 999999, 'accion' => 'invalid', 'origen' => 'test', 'datos_posteriores' => '{}']);
    }

    public function test_audit_rejects_two_subjects(): void
    {
        $usuario = Usuario::factory()->create(['rol_id' => 1]);
        $this->expectException(QueryException::class);
        DB::table('registros_auditoria_administrativa')->insert(['rol_id' => 1, 'usuario_id' => $usuario->id, 'accion' => 'invalid', 'origen' => 'test', 'datos_posteriores' => '{}']);
    }
}
