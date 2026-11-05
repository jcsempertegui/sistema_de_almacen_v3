<?php

namespace Tests\Feature\Catalog;

use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\Support\CargadorMigracionArchivada;
use Tests\TestCase;

class LocationPermissionInitializationTest extends TestCase
{
    private function codes(): array
    {
        return ['locations.view', 'locations.create', 'locations.update', 'locations.change_status'];
    }

    public function test_initial_capabilities_are_only_those_approved_for_each_role(): void
    {
        foreach ([1, 2] as $id) {
            $this->assertSame([], array_diff($this->codes(), Rol::findOrFail($id)->codigosPermisosAsignados()));
        }
        $auditor = Rol::findOrFail(3)->codigosPermisosAsignados();
        $this->assertContains('locations.view', $auditor);
        foreach (array_diff($this->codes(), ['locations.view']) as $codigo) {
            $this->assertNotContains($codigo, $auditor);
        }
        $this->assertDatabaseCount('permisos', 123);
        $this->assertDatabaseCount('permiso_rol', 260);
    }

    public function test_repeat_initialization_preserves_revocations_unknown_roles_and_existing_people(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $manager = Rol::findOrFail(2);
        $codes = array_diff($manager->codigosPermisosAsignados(), ['locations.create', 'locations.update', 'attributes.create']);
        app(GuardarRol::class)->handle($admin, $manager, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $unknown = Rol::create(['codigo' => 'location_unknown', 'nombre' => 'Administrador']);
        $usuario = Usuario::factory()->create(['rol_id' => $unknown->id]);
        $beforeUser = $usuario->fresh()->getAttributes();
        $beforeLinks = DB::table('permiso_rol')->orderBy('id')->get()->toJson();
        $beforeRoles = Rol::orderBy('id')->get()->toJson();
        $logs = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_14_000005_add_location_permissions.php'));
        $migration->up();
        $migration->up();
        $this->assertSame($beforeLinks, DB::table('permiso_rol')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeRoles, Rol::orderBy('id')->get()->toJson());
        $this->assertSame($logs, RegistroAuditoriaAdministrativa::count());
        $this->assertSame($beforeUser, $usuario->fresh()->getAttributes());
        $this->assertSame([], $unknown->codigosPermisosAsignados());
    }

    public function test_only_missing_grants_change_versions_audit_and_sessions_without_modifying_prior_data(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $this->signIn($actor);
        $ids = Permiso::whereIn('codigo', $this->codes())->pluck('id')->all();
        DB::table('permiso_rol')->where('rol_id', 2)->whereIn('permiso_id', $ids)->delete();
        $oldLinks = DB::table('permiso_rol')->whereNotIn('permiso_id', $ids)->orderBy('id')->get()->toJson();
        $versions = Rol::orderBy('id')->pluck('version_permisos', 'id')->all();
        $auditCount = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_14_000005_add_location_permissions.php'));
        $migration->up();
        $this->assertSame($versions[2] + 1, Rol::findOrFail(2)->version_permisos);
        foreach ([1, 3] as $id) {
            $this->assertSame($versions[$id], Rol::findOrFail($id)->version_permisos);
        }
        $this->assertSame($oldLinks, DB::table('permiso_rol')->whereNotIn('permiso_id', $ids)->orderBy('id')->get()->toJson());
        $this->assertSame($auditCount + 1, RegistroAuditoriaAdministrativa::count());
        $this->assertSame('migration', RegistroAuditoriaAdministrativa::latest('id')->firstOrFail()->origen);
        $this->getJson(route('ubicaciones.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->get(route('ubicaciones.create'))->assertOk();
        $migration->up();
        $this->assertSame($auditCount + 1, RegistroAuditoriaAdministrativa::count());
    }
}
