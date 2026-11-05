<?php

namespace Tests\Feature\Inventory;

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use App\Support\TestingDatabaseGuard;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

#[Group('concurrency')]
class CorrectionConcurrencyTest extends TestCase
{
    use CreatesInventory;

    public function beginDatabaseTransaction(): void {}

    public static function races(): array
    {
        return ['competing counts' => ['adjustment_race'], 'idempotent retry' => ['adjustment_retry'], 'duplicate compensation' => ['compensation_race'], 'revocation first' => ['auth_revoke_first'], 'execution first' => ['execute_before_revoke'], 'permiso revoked' => ['role_revoke_first'], 'authorizer rol disabled' => ['authorizer_role_disabled'], 'return compensation first' => ['compensation_before_loan'], 'new prestamo first' => ['loan_before_compensation']];
    }

    #[DataProvider('races')]
    public function test_real_wait_revalidates_balance_authorization_and_custody(string $scenario): void
    {
        $this->assertSame('sga_rio_grande_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('usuarios', 0);
        $c = null;
        $other = null;
        $rol = null;
        $trabajador = null;
        $processes = [];
        $streams = [];
        try {
            $individual = in_array($scenario, ['compensation_before_loan', 'loan_before_compensation']);
            $c = $this->inventoryContext($individual ? 'individual' : 'quantity');
            $rol = Rol::create(['codigo' => 's5_race_'.bin2hex(random_bytes(7)), 'nombre' => 'Prueba aislada', 'activo' => true]);
            foreach (Permiso::pluck('id') as $id) {
                DB::table('permiso_rol')->insert(['rol_id' => $rol->id, 'permiso_id' => $id, 'activo' => true]);
            }$other = Usuario::factory()->create(['rol_id' => $rol->id]);
            $svc = app(CorreccionesInventario::class);
            $authorization = null;
            $sourceKind = 'inventory-documents';
            if ($individual) {
                $c['producto']->update(['prestable' => true]);
                $receipt = $this->postInventory($c, 'receipt', '1');
                $trabajador = Trabajador::factory()->create();
                $loanData = ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]];
                $prestamo = app(GuardarPrestamo::class)->handle($c['actor'], null, $loanData);
                $prestamo = app(ConfirmarPrestamo::class)->handle($c['actor'], $prestamo, 1);
                $ret = app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
                $ret = app(ConfirmarDevolucion::class)->handle($c['actor'], $ret, 1);
                $sourceKind = 'returns';
                $sourceId = $ret->id;
                $authorization = app(GuardarPrestamo::class)->handle($other, null, $loanData);
            } else {
                $receipt = $this->postInventory($c, 'receipt', '3');
                $sourceId = $receipt->id;
            }
            $make = function () use ($svc, $c, $other, $scenario, $individual, $sourceKind, $sourceId) {
                $data = ['motivo' => 'Carrera aislada', 'fecha_documento' => '2026-10-20'];
                if ($scenario === 'compensation_race' || $individual) {
                    $data += ['tipo' => 'compensation', 'tipo_origen' => $sourceKind, 'origen_id' => $sourceId];
                } else {
                    $data += ['tipo' => 'adjustment', 'expected_line_count' => 1, 'lineas' => [['producto_id' => $c['producto']->id, 'ubicacion_id' => $c['ubicacion']->id, 'cantidad_contada' => '0', 'unit_ids' => []]]];
                }$doc = $svc->save($c['actor'], null, $data);
                $svc->transition($c['actor'], $doc, 'request', 1);

                return $svc->transition($other, $doc, 'approve', 1, 'Revisión independiente');
            };
            $a = $make();
            $b = in_array($scenario, ['adjustment_race', 'compensation_race']) ? $make() : $a;
            $clase = 'corrections';
            $operation = ['execute', 'execute'];
            if ($scenario === 'authorizer_role_disabled') {
                $operation = ['disable_authorizer_role', 'execute'];
            }
            if ($scenario === 'auth_revoke_first') {
                $operation = ['revoke', 'execute'];
            }if ($scenario === 'execute_before_revoke') {
                $operation = ['execute', 'revoke'];
            }if ($scenario === 'role_revoke_first') {
                $operation = ['revoke_role', 'execute'];
            }if ($scenario === 'compensation_before_loan') {
                $operation = ['execute', 'reloan'];
            }if ($scenario === 'loan_before_compensation') {
                $operation = ['reloan', 'execute'];
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
                $env[$key] = $_ENV[$key];
            }
            foreach ([$a, $b] as $index => $doc) {
                $stream = new InputStream;
                $job = ['actor' => $index && $scenario !== 'authorizer_role_disabled' ? $other->id : $c['actor']->id, 'documento' => $doc->id, 'tipo' => $clase, 'operation' => $operation[$index], 'index' => $index, 'rol' => $rol->id, 'authorization' => $authorization?->id];
                $p = new Process([PHP_BINARY, base_path('tests/Support/concurrent-correction.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 120);
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
                $expected = $index === 0 || in_array($scenario, ['adjustment_retry'], true) ? 'changed' : 'protected';
                $this->assertStringContainsString('RESULT:'.$expected, $p->getOutput());
            }
            preg_match('/LOCK_WAIT:([0-9.]+)/', $processes[1]->getOutput(), $wait);
            $this->assertGreaterThanOrEqual(100, (float) ($wait[1] ?? 0));
            $balance = (string) DB::table('existencias_ubicacion')->where('producto_id', $c['producto']->id)->sum('cantidad');
            $ledger = (string) DB::table('movimientos_inventario')->where('producto_id', $c['producto']->id)->selectRaw("SUM(CASE WHEN direccion='in' THEN cantidad ELSE -cantidad END) AS total")->first()->total;
            $this->assertSame($balance, $ledger);

            $expectedBalance = in_array($scenario, ['auth_revoke_first', 'role_revoke_first', 'authorizer_role_disabled']) ? '3.000000' : '0.000000';
            $this->assertSame($expectedBalance, $balance);
            if ($individual) {
                $this->assertSame(1, DB::table('custodias_unidad_individual')->where('producto_id', $c['producto']->id)->whereNotNull('ciclo_actual_id')->count());
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if ($c) {
                $producto = $c['producto']->id;
                $actors = array_filter([$c['actor']->id, $other?->id]);
                $docs = DB::table('correcciones_inventario')->whereIn('creado_por', $actors)->pluck('id');
                $lineas = DB::table('lineas_correccion_inventario')->whereIn('correccion_id', $docs)->pluck('id');
                $auths = DB::table('autorizaciones_correccion')->whereIn('correccion_id', $docs)->pluck('id');
                DB::table('posiciones_unidad_individual')->where('producto_id', $producto)->delete();
                DB::table('custodias_unidad_individual')->where('producto_id', $producto)->delete();
                DB::table('items_devolucion_herramienta')->where('producto_id', $producto)->update(['ciclo_custodia_id' => null]);
                $cycles = DB::table('ciclos_custodia_prestamo')->where('producto_id', $producto)->orderByDesc('id')->pluck('id');
                foreach ($cycles as $id) {
                    DB::table('cierres_ciclo_prestamo')->where('ciclo_id', $id)->delete();
                    DB::table('ciclos_custodia_prestamo')->where('id', $id)->delete();
                }
                $returnItems = DB::table('items_devolucion_herramienta')->where('producto_id', $producto)->pluck('id');
                DB::table('cierres_item_prestamo')->whereIn('item_devolucion_id', $returnItems)->delete();
                DB::table('compensaciones_documento')->whereIn('correccion_id', $docs)->delete();
                DB::table('movimientos_inventario')->whereIn('linea_correccion_id', $lineas)->delete();
                DB::table('unidades_linea_correccion')->whereIn('linea_correccion_id', $lineas)->delete();
                DB::table('lineas_correccion_inventario')->whereIn('id', $lineas)->delete();
                DB::table('eventos_autorizacion_correccion')->whereIn('autorizacion_id', $auths)->delete();
                DB::table('autorizaciones_correccion')->whereIn('id', $auths)->delete();
                DB::table('eventos_operacion_critica')->whereIn('correccion_id', $docs)->delete();
                DB::table('correcciones_inventario')->whereIn('id', $docs)->delete();
                DB::table('movimientos_inventario')->where('producto_id', $producto)->delete();
                foreach (['items_devolucion_herramienta', 'items_prestamo_herramienta', 'unidades_linea_documento', 'lineas_documento_inventario', 'existencias_ubicacion', 'unidades_individuales', 'ubicaciones_producto'] as $table) {
                    DB::table($table)->where('producto_id', $producto)->delete();
                }foreach (['devoluciones_herramientas', 'prestamos_herramientas', 'documentos_inventario'] as $table) {
                    DB::table($table)->whereIn('creado_por', $actors)->delete();
                }DB::table('productos')->where('id', $producto)->delete();
                DB::table('ubicaciones')->where('id', $c['ubicacion']->id)->delete();
                DB::table('categorias')->where('id', $c['producto']->categoria_id)->delete();
                DB::table('unidades_medida')->where('id', $c['producto']->unidad_medida_id)->delete();
                if ($trabajador) {
                    DB::table('trabajadores')->where('id', $trabajador->id)->delete();
                }if ($rol) {
                    DB::table('registros_auditoria_administrativa')->where('rol_id', $rol->id)->delete();
                    DB::table('roles')->where('id', $rol->id)->update(['creado_por' => null, 'modificado_por' => null]);
                }DB::table('usuarios')->whereIn('id', $actors)->delete();
                if ($rol) {
                    DB::table('permiso_rol')->where('rol_id', $rol->id)->delete();
                    DB::table('roles')->where('id', $rol->id)->delete();
                }
            }
        }
        $this->assertDatabaseCount('usuarios', 0);
    }
}
