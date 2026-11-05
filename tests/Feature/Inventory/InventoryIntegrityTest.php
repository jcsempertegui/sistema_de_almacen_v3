<?php

namespace Tests\Feature\Inventory;

use App\Models\DocumentoInventario;
use App\Models\MovimientoInventario;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\PermisosInventario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\CargadorMigracionArchivada;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InventoryIntegrityTest extends TestCase
{
    use CreatesInventory;

    public function test_sql_constraints_reject_negative_stock_duplicate_movements_and_wrong_unit_membership(): void
    {
        $c = $this->inventoryContext('individual');
        $doc = $this->postInventory($c, cantidad: '1');
        $line = $doc->lineas()->firstOrFail();
        $other = $this->inventoryContext('individual');
        $duplicate = (array) DB::table('movimientos_inventario')->first();
        unset($duplicate['id']);
        $operations = [
            fn () => DB::table('existencias_ubicacion')->where('producto_id', $c['producto']->id)->update(['cantidad' => '-1']),
            fn () => DB::table('movimientos_inventario')->insert($duplicate),
            fn () => DB::table('unidades_linea_documento')->insert(['linea_documento_id' => $line->id, 'documento_id' => $doc->id, 'producto_id' => $c['producto']->id, 'unidad_individual_id' => $other['unidad']->id]),
            fn () => DB::table('lineas_documento_inventario')->where('id', $line->id)->update(['cantidad' => '0']),
            fn () => DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $c['unidad']->id)->update(['ubicacion_id' => null]),
            fn () => DB::table('productos')->where('id', $c['producto']->id)->delete(),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('SQL integrity violation accepted');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
            }
        }
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '1.000000']);
    }

    public function test_confirmed_document_and_movement_models_are_immutable(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->postInventory($c);
        foreach ([fn () => $doc->update(['observaciones' => 'Rewrite']), fn () => $doc->delete(), fn () => MovimientoInventario::firstOrFail()->delete(), fn () => $doc->lineas()->firstOrFail()->update(['cantidad' => '99'])] as $operation) {
            try {
                $operation();
                $this->fail('Immutable history changed');
            } catch (\LogicException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_new_permissions_preserve_revocations_unknown_roles_and_initial_configuration(): void
    {
        $this->assertDatabaseCount('permisos', 123);
        $this->assertDatabaseCount('permiso_rol', 260);
        $this->assertContains('initial_stock.confirm', Rol::findOrFail(1)->codigosPermisosAsignados());
        $this->assertNotContains('initial_stock.confirm', Rol::findOrFail(2)->codigosPermisosAsignados());
        foreach (array_keys(PermisosInventario::LABELS) as $codigo) {
            $this->assertSame(str_ends_with($codigo, '.view'), in_array($codigo, Rol::findOrFail(3)->codigosPermisosAsignados(), true));
        }
        $permiso = Permiso::where('codigo', 'remittances.create')->firstOrFail();
        DB::table('permiso_rol')->where('rol_id', 2)->where('permiso_id', $permiso->id)->update(['activo' => false]);
        $unknown = Rol::create(['codigo' => 'inventory_unknown', 'nombre' => 'Administrador']);
        $usuario = Usuario::factory()->create(['rol_id' => $unknown->id]);
        $before = $usuario->fresh()->getAttributes();
        $versions = Rol::pluck('version_permisos', 'id')->all();
        $audit = DB::table('registros_auditoria_administrativa')->count();
        $migration = CargadorMigracionArchivada::cargar(database_path('migrations/2026_10_19_000002_add_inventory_permissions.php'));
        $migration->up();
        $migration->up();
        $this->assertNotContains('remittances.create', Rol::findOrFail(2)->codigosPermisosAsignados());
        $this->assertSame([], $unknown->codigosPermisosAsignados());
        $this->assertSame($before, $usuario->fresh()->getAttributes());
        $this->assertSame($versions, Rol::pluck('version_permisos', 'id')->all());
        $this->assertSame($audit, DB::table('registros_auditoria_administrativa')->count());
    }

    public function test_csrf_rejects_confirmation_without_a_token(): void
    {
        $c = $this->inventoryContext();
        $doc = $this->inventoryDraft($c);
        // Force the real middleware path instead of Laravel's unidad-test bypass.
        $this->app['env'] = 'local';
        try {
            $this->signIn($c['actor'])->post(route('documentos-inventario.confirm', $doc), ['version_bloqueo' => 1])->assertStatus(419);
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame('draft', DocumentoInventario::findOrFail($doc->id)->estado);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }
}
