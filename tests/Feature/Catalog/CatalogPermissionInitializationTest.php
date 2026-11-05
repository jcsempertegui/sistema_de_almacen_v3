<?php

namespace Tests\Feature\Catalog;

use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\CargadorMigracionArchivada;
use Tests\TestCase;

class CatalogPermissionInitializationTest extends TestCase
{
    public function test_initial_role_capabilities_include_only_the_approved_catalog_permissions(): void
    {
        $codes = ['categories.view', 'categories.create', 'categories.update', 'categories.change_status', 'measurement_units.view', 'measurement_units.create', 'measurement_units.update', 'measurement_units.change_status'];
        foreach ([1, 2] as $id) {
            $this->assertSame([], array_diff($codes, Rol::findOrFail($id)->codigosPermisosAsignados()));
        }
        $auditor = Rol::findOrFail(3)->codigosPermisosAsignados();
        $this->assertContains('categories.view', $auditor);
        $this->assertContains('measurement_units.view', $auditor);
        foreach (array_diff($codes, ['categories.view', 'measurement_units.view']) as $codigo) {
            $this->assertNotContains($codigo, $auditor);
        }
        $this->assertDatabaseCount('permisos', 123);
    }

    public function test_repeat_initialization_preserves_revocations_unknown_roles_users_and_audit(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $manager = Rol::findOrFail(2);
        $codes = array_diff($manager->codigosPermisosAsignados(), ['categories.create', 'measurement_units.update']);
        app(GuardarRol::class)->handle($admin, $manager, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $unknown = Rol::create(['codigo' => 'desconocido', 'nombre' => 'Administrador']);
        $usuario = Usuario::factory()->create(['rol_id' => $unknown->id]);
        $userBefore = $usuario->fresh()->getAttributes();
        $version = $manager->fresh()->version_permisos;
        $logs = RegistroAuditoriaAdministrativa::count();
        $links = DB::table('permiso_rol')->count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php'));
        $migration->up();
        $migration->up();
        $this->seed(DatabaseSeeder::class);
        $this->assertNotContains('categories.create', $manager->fresh()->codigosPermisosAsignados());
        $this->assertNotContains('measurement_units.update', $manager->fresh()->codigosPermisosAsignados());
        $this->assertSame($version, $manager->fresh()->version_permisos);
        $this->assertSame($logs, RegistroAuditoriaAdministrativa::count());
        $this->assertSame($links, DB::table('permiso_rol')->count());
        $this->assertSame([], $unknown->codigosPermisosAsignados());
        $this->assertSame($userBefore, $usuario->fresh()->getAttributes());
    }

    public function test_initialization_of_missing_links_versions_and_audits_only_changed_roles(): void
    {
        // Simulate the pre-rollout state in this rollback-only test transaction.
        $ids = Permiso::where('codigo', 'like', 'categories.%')->orWhere('codigo', 'like', 'measurement_units.%')->pluck('id')->all();
        DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
        $versions = Rol::orderBy('id')->pluck('version_permisos', 'id')->all();
        $auditCount = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php'));
        $migration->up();
        foreach (Rol::orderBy('id')->get() as $rol) {
            $this->assertSame($versions[$rol->id] + 1, $rol->version_permisos);
        }
        $this->assertSame($auditCount + 3, RegistroAuditoriaAdministrativa::count());
        $this->assertDatabaseCount('permiso_rol', 260);
        $migration->up();
        $this->assertSame($auditCount + 3, RegistroAuditoriaAdministrativa::count());
    }

    public function test_new_grants_invalidate_existing_sessions_without_touching_other_roles(): void
    {
        $manager = Usuario::factory()->create(['rol_id' => 2]);
        $this->signIn($manager);
        $versions = Rol::orderBy('id')->pluck('version_permisos', 'id')->all();
        $ids = Permiso::where('codigo', 'like', 'categories.%')->orWhere('codigo', 'like', 'measurement_units.%')->pluck('id')->all();
        DB::table('permiso_rol')->where('rol_id', 2)->whereIn('permiso_id', $ids)->delete();
        $logs = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_13_000003_add_category_and_measurement_unit_permissions.php'));
        $migration->up();
        $this->assertSame($versions[2] + 1, Rol::findOrFail(2)->version_permisos);
        foreach ([1, 3] as $id) {
            $this->assertSame($versions[$id], Rol::findOrFail($id)->version_permisos);
        }
        $this->assertSame($logs + 1, RegistroAuditoriaAdministrativa::count());
        $this->getJson(route('categorias.index'))->assertUnauthorized();
        $this->signIn($manager->fresh())->get(route('categorias.create'))->assertOk();
    }
}
