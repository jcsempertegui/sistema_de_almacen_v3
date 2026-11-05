<?php

namespace Tests\Feature\Inventory;

use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Entregas\GuardarEntrega;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Inventario\GuardarDocumentoInventario;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use App\Services\RegularizacionDevolucion;
use App\Support\TestingDatabaseGuard;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

#[Group('concurrency')]
class WorkerOperationConcurrencyTest extends TestCase
{
    use CreatesInventory;

    // Independent processes need committed fixtures. Cleanup is restricted to IDs created here.
    public function beginDatabaseTransaction(): void {}

    public static function races(): array
    {
        return ['competing deliveries' => ['delivery'], 'exclusive custody' => ['prestamo'], 'prestamo retry' => ['loan_retry'], 'competing returns' => ['return'], 'return retry' => ['return_retry'], 'permiso revocation' => ['revoke'], 'trabajador deactivation' => ['trabajador'], 'authorization revoked first' => ['regularization'], 'return protects used approval' => ['regularization_reverse'], 'inactive identity blocks ordinary return' => ['return_disable_first'], 'received identity blocks deactivation' => ['return_disable_last']];
    }

    #[DataProvider('races')]
    public function test_atomic_current_state_under_real_sql_wait(string $scenario): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sga_rio_grande_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('usuarios')->count());
        $c = null;
        $other = null;
        $rol = null;
        $trabajador = null;
        $processes = [];
        $streams = [];
        try {
            $c = $this->inventoryContext($scenario === 'delivery' ? 'quantity' : 'individual');
            $rol = Rol::create(['codigo' => 's4_race_'.bin2hex(random_bytes(7)), 'nombre' => 'Prueba aislada', 'activo' => true]);
            foreach (Permiso::pluck('id') as $id) {
                DB::table('permiso_rol')->insert(['rol_id' => $rol->id, 'permiso_id' => $id, 'activo' => true]);
            }
            $other = Usuario::factory()->create(['rol_id' => $rol->id]);
            $trabajador = Trabajador::factory()->create();
            $clase = $scenario === 'delivery' ? 'deliveries' : 'loans';
            $operation = ['confirm', 'confirm'];
            $authorization = null;
            if ($clase === 'deliveries') {
                $this->postInventory($c, 'receipt', '10');
                $data = ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad' => '7', 'unit_ids' => []]]];
                $a = app(GuardarEntrega::class)->handle($c['actor'], null, $data);
                $b = app(GuardarEntrega::class)->handle($other, null, $data);
            } else {
                $c['producto']->update(['prestable' => true]);
                if (in_array($scenario, ['prestamo', 'loan_retry'], true)) {
                    $extra = UnidadIndividual::factory()->create(['producto_id' => $c['producto']->id, 'ubicacion_id' => null]);
                    $receiptData = $this->inventoryData($c, 'receipt', '2');
                    $receiptData['lineas'][0]['unit_ids'][] = $extra->id;
                    $receipt = app(GuardarDocumentoInventario::class)->handle($c['actor'], null, $receiptData);
                    app(ConfirmarDocumentoInventario::class)->handle($c['actor'], $receipt, 1);
                } else {
                    $this->postInventory($c, 'receipt', '1');
                }
                $data = ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]];
                $a = app(GuardarPrestamo::class)->handle($c['actor'], null, $data);
                $b = $scenario === 'loan_retry' ? $a : app(GuardarPrestamo::class)->handle($other, null, $data);
                if (str_starts_with($scenario, 'return') || str_starts_with($scenario, 'regularization')) {
                    $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $a, 1);
                    $clase = 'returns';
                    if (str_starts_with($scenario, 'regularization')) {
                        DB::table('unidades_individuales')->where('id', $c['unidad']->id)->update(['activo' => false]);
                    }
                    $data = ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-19', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]];
                    $a = app(GuardarDevolucion::class)->handle($c['actor'], null, $data);
                    $b = $scenario === 'return' ? app(GuardarDevolucion::class)->handle($other, null, $data) : $a;
                    if ($scenario === 'return_disable_first') {
                        $operation = ['disable_unit', 'confirm'];
                    }if ($scenario === 'return_disable_last') {
                        $operation = ['confirm', 'disable_unit'];
                    }
                    if (str_starts_with($scenario, 'regularization')) {
                        $authorization = app(RegularizacionDevolucion::class)->request($c['actor'], $a->lineas()->first(), 1, 'Prueba concurrente');
                        $authorization = app(RegularizacionDevolucion::class)->decide($c['actor'], $authorization, true, 'Identidad revisada');
                        $operation = $scenario === 'regularization' ? ['revoke_regularization', 'confirm'] : ['confirm', 'revoke_regularization'];
                    }
                }
                if ($scenario === 'revoke') {
                    $operation = ['revoke', 'confirm'];
                }if ($scenario === 'trabajador') {
                    $operation = ['disable_worker', 'confirm'];
                }
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
                $env[$key] = $_ENV[$key];
            }
            foreach ([$a, $b] as $index => $doc) {
                $stream = new InputStream;
                $job = ['actor' => $index ? $other->id : $c['actor']->id, 'documento' => $doc->id, 'tipo' => $clase, 'operation' => $operation[$index], 'index' => $index, 'rol' => $rol->id, 'authorization' => $authorization?->id];
                $p = new Process([PHP_BINARY, base_path('tests/Support/concurrent-worker-operation.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 120);
                $p->start();
                $processes[] = $p;
                $streams[] = $stream;
            }
            $phase = 'READY';
            $await = function (callable $ready) use (&$processes, &$phase) {
                do {
                    foreach ($processes as $p) {
                        try {
                            $p->checkTimeout();
                        } catch (\Throwable $e) {
                            $this->fail($phase.': '.json_encode(array_map(fn ($p) => [$p->getOutput(), $p->getErrorOutput()], $processes)));
                        }if (! $p->isRunning() && ! str_contains($p->getOutput(), 'RESULT:')) {
                            $this->fail($phase.': '.$p->getOutput().$p->getErrorOutput());
                        }
                    }if ($ready()) {
                        return;
                    }usleep(10000);
                } while (true);
            };
            $await(fn () => str_contains($processes[0]->getOutput(), 'READY') && str_contains($processes[1]->getOutput(), 'READY'));
            $phase = 'LOCKED';
            $streams[0]->write("GO\n");
            $await(fn () => str_contains($processes[0]->getOutput(), 'LOCKED'));
            $streams[1]->write("GO\n");
            $streams[1]->close();
            preg_match('/CONNECTION_ID:([0-9]+)/', $processes[1]->getOutput(), $match);
            $connection = (int) ($match[1] ?? 0);
            $this->assertGreaterThan(0, $connection);
            $phase = 'SQL wait';
            $await(function () use ($connection, &$processes) {
                if (! $processes[1]->isRunning()) {
                    $this->fail('Premature competitor: '.$processes[1]->getOutput().$processes[1]->getErrorOutput());
                }

                // MariaDB refreshes this cache only after more than 100 ms without reads.
                usleep(150000);

                return DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id=?', [$connection])->n > 0;
            });
            usleep(150000);
            $streams[0]->write("RELEASE\n");
            $streams[0]->close();
            $phase = 'completion';
            $await(fn () => ! $processes[0]->isRunning() && ! $processes[1]->isRunning());
            foreach ($processes as $index => $p) {
                $this->assertSame(0, $p->wait(), $p->getErrorOutput());
                $expected = $index === 0 || in_array($scenario, ['loan_retry', 'return_retry'], true) ? 'changed' : 'protected';
                $this->assertStringContainsString('RESULT:'.$expected, $p->getOutput());
            }
            preg_match('/LOCK_WAIT:([0-9.]+)/', $processes[1]->getOutput(), $wait);
            $this->assertGreaterThanOrEqual(100, (float) ($wait[1] ?? 0));
            $balance = (string) DB::table('existencias_ubicacion')->where('producto_id', $c['producto']->id)->sum('cantidad');
            $ledger = (string) DB::table('movimientos_inventario')->where('producto_id', $c['producto']->id)->selectRaw("SUM(CASE WHEN direccion='in' THEN cantidad ELSE -cantidad END) AS total")->first()->total;
            $this->assertSame($balance, $ledger);
            $expectedBalance = $scenario === 'delivery' ? '3.000000' : (in_array($scenario, ['prestamo', 'loan_retry', 'return', 'return_retry', 'regularization_reverse', 'return_disable_last', 'revoke', 'trabajador'], true) ? '1.000000' : '0.000000');
            $this->assertSame($expectedBalance, $balance);
            $this->assertSame(in_array($scenario, ['return', 'return_retry', 'regularization_reverse', 'return_disable_last'], true) ? 1 : 0, DB::table('cierres_item_prestamo')->count());
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
            if ($c) {
                $producto = $c['producto']->id;
                $returnItems = DB::table('items_devolucion_herramienta')->where('producto_id', $producto)->pluck('id');
                $auth = DB::table('autorizaciones_regularizacion_devolucion')->whereIn('item_devolucion_id', $returnItems)->pluck('id');
                // Remove only cycles created by this committed race fixture.
                $cycles = DB::table('ciclos_custodia_prestamo')->where('producto_id', $producto)->orderByDesc('id')->pluck('id');
                DB::table('custodias_unidad_individual')->where('producto_id', $producto)->delete();
                DB::table('items_devolucion_herramienta')->whereIn('id', $returnItems)->update(['ciclo_custodia_id' => null]);
                DB::table('cierres_ciclo_prestamo')->whereIn('ciclo_id', $cycles)->delete();
                foreach ($cycles as $cycleId) {
                    DB::table('ciclos_custodia_prestamo')->where('id', $cycleId)->delete();
                }
                DB::table('posiciones_unidad_individual')->where('producto_id', $producto)->delete();
                DB::table('cierres_item_prestamo')->whereIn('item_devolucion_id', $returnItems)->delete();
                DB::table('custodias_unidad_individual')->where('producto_id', $producto)->delete();
                DB::table('eventos_regularizacion_devolucion')->whereIn('autorizacion_id', $auth)->delete();
                DB::table('autorizaciones_regularizacion_devolucion')->whereIn('id', $auth)->delete();
                DB::table('movimientos_inventario')->where('producto_id', $producto)->delete();
                foreach (['items_devolucion_herramienta', 'items_prestamo_herramienta', 'unidades_linea_entrega', 'lineas_entrega_material', 'unidades_linea_documento', 'lineas_documento_inventario', 'productos_existencia_inicial', 'existencias_ubicacion', 'unidades_individuales', 'ubicaciones_producto'] as $table) {
                    DB::table($table)->where('producto_id', $producto)->delete();
                }
                foreach (['devoluciones_herramientas', 'prestamos_herramientas', 'entregas_materiales', 'documentos_inventario'] as $table) {
                    DB::table($table)->whereIn('creado_por', array_filter([$c['actor']->id, $other?->id]))->delete();
                }
                DB::table('productos')->where('id', $producto)->delete();
                DB::table('ubicaciones')->where('id', $c['ubicacion']->id)->delete();
                DB::table('categorias')->where('id', $c['producto']->categoria_id)->delete();
                DB::table('unidades_medida')->where('id', $c['producto']->unidad_medida_id)->delete();
            }
            if ($trabajador) {
                DB::table('trabajadores')->where('id', $trabajador->id)->delete();
            }if ($rol) {
                DB::table('registros_auditoria_administrativa')->where('rol_id', $rol->id)->delete();
                DB::table('roles')->where('id', $rol->id)->update(['creado_por' => null, 'modificado_por' => null]);
            }
            DB::table('usuarios')->whereIn('id', array_filter([$c ? $c['actor']->id : null, $other?->id]))->delete();
            if ($rol) {
                DB::table('permiso_rol')->where('rol_id', $rol->id)->delete();
                DB::table('roles')->where('id', $rol->id)->delete();
            }
        }
        $this->assertSame(0, DB::table('usuarios')->count());
    }
}
