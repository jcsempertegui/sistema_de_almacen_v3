<?php

namespace Tests\Feature\Inventory;

use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CargadorMigracionArchivada;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class ControlIntegrityTest extends TestCase
{
    use CreatesInventory;

    public function test_permissions_initialization_preserves_revocations_people_and_unknown_roles(): void
    {
        $this->assertDatabaseCount('permisos', 123);
        $this->assertDatabaseCount('permiso_rol', 260);
        foreach ([1 => 123, 2 => 96, 3 => 41] as $id => $count) {
            $this->assertCount($count, Rol::findOrFail($id)->codigosPermisosAsignados());
        }
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $rol = Rol::findOrFail(2);
        $codes = array_diff($rol->codigosPermisosAsignados(), ['adjustments.execute']);
        app(GuardarRol::class)->handle($admin, $rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $unknown = Rol::create(['codigo' => 'custom_s5', 'nombre' => 'Administrador']);
        $person = Usuario::factory()->create(['rol_id' => $unknown->id]);
        $before = $person->fresh()->getAttributes();
        $links = DB::table('permiso_rol')->orderBy('id')->get()->toJson();
        $versions = Rol::pluck('version_permisos', 'id')->all();
        $logs = DB::table('registros_auditoria_administrativa')->count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_20_000005_add_control_permissions.php'));
        $migration->up();
        $migration->up();
        $this->assertSame($links, DB::table('permiso_rol')->orderBy('id')->get()->toJson());
        $this->assertSame($versions, Rol::pluck('version_permisos', 'id')->all());
        $this->assertSame($logs, DB::table('registros_auditoria_administrativa')->count());
        $this->assertSame($before, $person->fresh()->getAttributes());
        $this->assertSame([], $unknown->codigosPermisosAsignados());
    }

    public function test_sql_rejects_fifth_source_without_matching_metadata_and_wrong_product_units(): void
    {
        $c = $this->inventoryContext();
        $doc = app(CorreccionesInventario::class)->save($c['actor'], null, ['tipo' => 'adjustment', 'fecha_documento' => '2026-10-20', 'motivo' => 'Conteo', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad_contada' => '2', 'unit_ids' => []]]]);
        $line = $doc->lineas()->first();
        try {
            DB::table('movimientos_inventario')->insert(['linea_correccion_id' => $line->id, 'producto_id' => $line->producto_id, 'ubicacion_id' => $line->ubicacion_id, 'direccion' => 'in', 'cantidad' => '2', 'creado_por' => $c['actor']->id, 'fecha_registro' => now()]);
            $this->fail('Missing metadata accepted');
        } catch (QueryException $e) {
            $this->assertNotEmpty($e->getCode());
        }
        $other = $this->inventoryContext('individual');
        try {
            DB::table('unidades_linea_correccion')->insert(['linea_correccion_id' => $line->id, 'unidad_individual_id' => $other['unidad']->id, 'producto_id' => $other['producto']->id]);
            $this->fail('Wrong producto accepted');
        } catch (QueryException $e) {
            $this->assertSame('23000', (string) $e->getCode());
        }
    }

    public function test_revoked_control_permission_invalidates_session(): void
    {
        $manager = Usuario::factory()->create(['rol_id' => 2]);
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $this->signIn($manager)->get(route('corrections.create', 'adjustment'))->assertOk();
        $codes = array_diff($manager->rol->codigosPermisosAsignados(), ['adjustments.execute']);
        app(GuardarRol::class)->handle($admin, $manager->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->postJson(route('corrections.store', 'adjustment'), [])->assertUnauthorized();
        $this->assertDatabaseCount('correcciones_inventario', 0);
    }

    public function test_new_write_routes_keep_csrf_protection(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $this->signIn($admin);
        $original = $this->app['env'];
        $this->app->instance('env', 'production');
        try {
            $this->post(route('corrections.store', 'adjustment'), [])->assertStatus(419);
            $this->post(route('backups.request'), [])->assertStatus(419);
            $this->assertDatabaseCount('correcciones_inventario', 0);
            $this->assertDatabaseCount('ejecuciones_respaldo', 0);
        } finally {
            $this->app->instance('env', $original);
        }
    }
}
