<?php

namespace Tests\Feature;

use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class ReadQueryPerformanceTest extends TestCase
{
    use CreatesInventory;

    public function test_read_pages_keep_results_while_recording_query_counts(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '4');
        $this->signIn($c['actor']);
        $mediciones = [];
        foreach (['panel' => route('dashboard'), 'productos' => route('productos.index'), 'reporte_existencias' => route('reportes-control.index', 'stock')] as $pagina => $url) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->get($url)->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            $permisos = array_filter($queries, fn ($q) => str_contains($q['query'], 'from `permisos`'));
            $mediciones[$pagina] = ['consultas' => count($queries), 'consultas_permisos' => count($permisos)];
            $this->assertLessThanOrEqual(1, count($permisos), 'Los permisos del actor no deben consultarse por cada enlace.');
            $this->assertLessThanOrEqual($pagina === 'panel' ? 18 : 12, count($queries), 'Se excedió el presupuesto de consultas de lectura.');
            if ($pagina === 'panel') {
                $this->assertSame(['with_stock' => 1, 'without_stock' => 0], $response->viewData('operations')['stock']);
            } elseif ($pagina === 'reporte_existencias') {
                $this->assertSame(1, $response->viewData('rows')->total());
                $this->assertSame(1, $response->viewData('summary')['Registros']);
            }
        }
        $salida = getenv('SGA_QUERY_EVIDENCE');
        if ($salida) {
            $directorio = realpath(dirname($salida));
            $this->assertNotFalse($directorio);
            $this->assertStringStartsNotWith(strtolower(str_replace('\\', '/', base_path())).'/', strtolower(str_replace('\\', '/', $directorio)).'/');
            file_put_contents($salida, json_encode($mediciones, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_nested_mutations_recheck_permissions_and_the_read_scope_is_released_on_exceptions(): void
    {
        $usuario = Usuario::factory()->create(['rol_id' => 1]);
        $rol = $usuario->rol;
        $permiso = Permiso::where('codigo', 'workers.view')->firstOrFail();
        try {
            $rol->duranteConsulta(function () use ($usuario, $permiso) {
                $this->assertTrue($usuario->tienePermiso('workers.view'));
                DB::transaction(function () use ($usuario, $permiso) {
                    DB::table('permiso_rol')->where('rol_id', 1)->where('permiso_id', $permiso->id)->update(['activo' => false]);
                    $this->assertFalse($usuario->tienePermiso('workers.view'), 'Una transacción no debe autorizar con la lectura anterior.');
                });
                throw new RuntimeException('Interrupción de lectura');
            });
        } catch (RuntimeException $e) {
            $this->assertSame('Interrupción de lectura', $e->getMessage());
        }
        $this->assertFalse($usuario->tienePermiso('workers.view'), 'La copia temporal debe desaparecer incluso si falla la respuesta.');
    }

    public function test_permission_revocation_invalidates_the_next_request_and_cannot_reuse_a_previous_response(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $usuario = Usuario::factory()->create(['rol_id' => 2]);
        $this->signIn($usuario)->get(route('trabajadores.index'))->assertOk();
        $rol = Rol::findOrFail(2);
        $codes = array_diff($rol->codigosPermisosAsignados(), ['workers.view']);
        app(GuardarRol::class)->handle($admin, $rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->get(route('trabajadores.index'))->assertRedirect(route('login'));
        $this->signIn($usuario->fresh())->get(route('trabajadores.index'))->assertForbidden();
    }
}
