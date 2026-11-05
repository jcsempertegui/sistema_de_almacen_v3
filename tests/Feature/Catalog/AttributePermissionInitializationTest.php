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

class AttributePermissionInitializationTest extends TestCase
{
    private function codes(): array
    {
        return ['attributes.view', 'attributes.create', 'attributes.update', 'attributes.change_status', 'category_attributes.view', 'category_attributes.create', 'category_attributes.update', 'category_attributes.change_status'];
    }

    public function test_initial_roles_receive_approved_capabilities_and_auditor_only_reads(): void
    {
        foreach ([1, 2] as $id) {
            $this->assertSame([], array_diff($this->codes(), Rol::findOrFail($id)->codigosPermisosAsignados()));
        }
        $auditor = Rol::findOrFail(3)->codigosPermisosAsignados();
        $this->assertContains('attributes.view', $auditor);
        $this->assertContains('category_attributes.view', $auditor);
        foreach (array_diff($this->codes(), ['attributes.view', 'category_attributes.view']) as $codigo) {
            $this->assertNotContains($codigo, $auditor);
        }
        $this->assertDatabaseCount('permisos', 123);
        $this->assertDatabaseCount('permiso_rol', 260);
    }

    public function test_reinitialization_preserves_revoked_old_and_new_permissions_unknown_roles_and_users(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $manager = Rol::findOrFail(2);
        $codes = array_diff($manager->codigosPermisosAsignados(), ['attributes.create', 'category_attributes.update', 'categories.create']);
        app(GuardarRol::class)->handle($admin, $manager, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $unknown = Rol::create(['codigo' => 'unknown_technical', 'nombre' => 'Administrador']);
        $usuario = Usuario::factory()->create(['rol_id' => $unknown->id]);
        $before = $usuario->fresh()->getAttributes();
        $links = DB::table('permiso_rol')->orderBy('id')->get()->toJson();
        $roles = Rol::orderBy('id')->get()->toJson();
        $logs = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_14_000003_add_attribute_permissions.php'));
        $migration->up();
        $migration->up();
        $this->assertSame($links, DB::table('permiso_rol')->orderBy('id')->get()->toJson());
        $this->assertSame($roles, Rol::orderBy('id')->get()->toJson());
        $this->assertSame($logs, RegistroAuditoriaAdministrativa::count());
        $this->assertSame([], $unknown->codigosPermisosAsignados());
        $this->assertSame($before, $usuario->fresh()->getAttributes());
    }

    public function test_new_links_invalidate_affected_sessions_and_preserve_existing_records_and_permissions(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $this->signIn($actor);
        $ids = Permiso::whereIn('codigo', $this->codes())->pluck('id')->all();
        DB::table('permiso_rol')->where('rol_id', 2)->whereIn('permiso_id', $ids)->delete();
        $oldLinks = DB::table('permiso_rol')->whereNotIn('permiso_id', $ids)->orderBy('id')->get()->toJson();
        $versions = Rol::orderBy('id')->pluck('version_permisos', 'id')->all();
        $userBefore = $actor->fresh()->getAttributes();
        $auditCount = RegistroAuditoriaAdministrativa::count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_14_000003_add_attribute_permissions.php'));
        $migration->up();
        $this->assertSame($versions[2] + 1, Rol::findOrFail(2)->version_permisos);
        foreach ([1, 3] as $id) {
            $this->assertSame($versions[$id], Rol::findOrFail($id)->version_permisos);
        }
        $this->assertSame($oldLinks, DB::table('permiso_rol')->whereNotIn('permiso_id', $ids)->orderBy('id')->get()->toJson());
        $this->assertSame($userBefore, $actor->fresh()->getAttributes());
        $this->assertSame($auditCount + 1, RegistroAuditoriaAdministrativa::count());
        $log = RegistroAuditoriaAdministrativa::latest('id')->firstOrFail();
        $this->assertSame('migration', $log->origen);
        $this->assertSame(2, $log->rol_id);
        $this->getJson(route('atributos.index'))->assertUnauthorized();
        $this->signIn($actor->fresh())->get(route('atributos.create'))->assertOk();
        $migration->up();
        $this->assertSame($auditCount + 1, RegistroAuditoriaAdministrativa::count());
    }
}
