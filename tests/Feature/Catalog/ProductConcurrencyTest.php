<?php

namespace Tests\Feature\Catalog;

use App\Support\TestingDatabaseGuard;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class ProductConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['same codigo' => [['create', 'create']], 'stale edit' => [['edit', 'edit']], 'disable versus link' => [['disable', 'link']], 'link versus disable' => [['link', 'disable']], 'required versus producto' => [['required', 'create']], 'producto versus required' => [['create', 'required']], 'revocation versus producto' => [['revoke', 'create']]];
    }

    #[DataProvider('races')]
    public function test_concurrent_writers_revalidate_current_state_after_the_mutex(array $operations): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = TestingDatabaseGuard::pdo();
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('REPEATABLE-READ', $pdo->query('SELECT @@tx_isolation')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $nonce = bin2hex(random_bytes(7));
        $codigo = 'RACE-P-'.$nonce;
        $ids = [];
        $roles = [];
        $usuarios = [];
        $processes = [];
        $streams = [];
        $insert = function (string $sql, array $values) use ($pdo): int {
            $q = $pdo->prepare($sql);
            $q->execute($values);

            return (int) $pdo->lastInsertId();
        };
        try {
            for ($i = 0; $i < 2; $i++) {
                $roles[] = $rol = $insert('INSERT INTO roles (codigo,nombre,activo,version_permisos) VALUES (?,?,1,1)', ['product_race_'.$nonce.'_'.$i, 'Prueba']);
                $q = $pdo->prepare('INSERT INTO permiso_rol (rol_id,permiso_id,activo) SELECT ?,id,1 FROM permisos');
                $q->execute([$rol]);
                $usuarios[] = $insert('INSERT INTO usuarios (nombre,correo,contrasena,rol_id,activo,version_sesion) VALUES (?,?,?,?,1,1)', ['Prueba', $nonce.'_'.$i.'@example.test', password_hash('Prueba2026!', PASSWORD_BCRYPT, ['cost' => 4]), $rol]);
            }
            $ids['categoria'] = $insert('INSERT INTO categorias (codigo,nombre,activo) VALUES (?,?,1)', [$codigo, 'Categoría concurrente']);
            $ids['unidad'] = $insert("INSERT INTO unidades_medida (codigo,nombre,simbolo,dimension,decimales,activo) VALUES (?,?,?,'count',0,1)", [$codigo, 'Unidad', $nonce]);
            $ids['ubicacion'] = $insert("INSERT INTO ubicaciones (codigo,nombre,tipo,activo,permite_almacenar) VALUES (?,?,'sector',1,1)", [$codigo, 'Ubicación']);
            $ids['atributo'] = $insert("INSERT INTO atributos (codigo,nombre,tipo_dato,activo) VALUES (?,?,'text',1)", [$codigo, 'Identificador']);
            $ids['association'] = $insert('INSERT INTO atributos_categoria (categoria_id,atributo_id,obligatorio,activo) VALUES (?,?,0,1)', [$ids['categoria'], $ids['atributo']]);
            $ids['producto'] = null;
            if (in_array('edit', $operations, true) || in_array('link', $operations, true)) {
                $ids['producto'] = $insert("INSERT INTO productos (codigo_interno,nombre,categoria_id,unidad_medida_id,modo_control,activo) VALUES (?,?,?,?,'quantity',1)", [$codigo.'-BASE', 'Base', $ids['categoria'], $ids['unidad']]);
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $nombre) {
                $env[$nombre] = $_ENV[$nombre];
            }
            foreach ($operations as $i => $operation) {
                $job = $ids + ['actor' => $usuarios[$i], 'index' => $i, 'operation' => $operation, 'other_role' => $roles[1], 'codigo' => $codigo.'-NEW'];
                $stream = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-product.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 120);
                $process->start();
                $processes[] = $process;
                $streams[] = $stream;
            }
            $phase = 'arranque';
            $started = microtime(true);
            $await = function (callable $ready) use (&$processes, &$phase, $started, $pdo): void {
                do {
                    foreach ($processes as $process) {
                        try {
                            $process->checkTimeout();
                        } catch (ProcessTimedOutException $e) {
                            $diagnostics = ['phase' => $phase, 'elapsed' => microtime(true) - $started,
                                'trabajadores' => array_map(fn ($p) => ['stdout' => $p->getOutput(), 'stderr' => $p->getErrorOutput(), 'running' => $p->isRunning()], $processes),
                                'waits' => $pdo->query('SELECT * FROM information_schema.INNODB_LOCK_WAITS')->fetchAll(PDO::FETCH_ASSOC)];
                            throw new \RuntimeException(json_encode($diagnostics, JSON_THROW_ON_ERROR), previous: $e);
                        }
                        if (! $process->isRunning() && ! str_contains($process->getOutput(), 'RESULT:')) {
                            $this->fail($phase.': '.$process->getOutput().' '.$process->getErrorOutput());
                        }
                    }
                    if ($ready()) {
                        return;
                    } usleep(10000);
                } while (true);
            };
            $await(fn () => count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'READY'))) === 2);
            $streams[0]->write("GO\n");
            $phase = 'mutex del ganador';
            $await(fn () => str_contains($processes[0]->getOutput(), 'LOCKED'));
            $streams[1]->write("GO\n");
            $streams[1]->close();
            preg_match('/CONNECTION_ID:([0-9]+)/', $processes[1]->getOutput(), $connectionMatch);
            $waitingConnection = (int) ($connectionMatch[1] ?? 0);
            $this->assertGreaterThan(0, $waitingConnection);
            $phase = 'espera SQL del competidor';
            // Release the winner only AFTER MariaDB confirms the second connection is blocked.
            $await(function () use ($pdo, $waitingConnection, &$processes) {
                if (! $processes[1]->isRunning()) {
                    $this->fail('El competidor terminó antes de observar su bloqueo: '.$processes[1]->getOutput().' '.$processes[1]->getErrorOutput());
                }
                // MariaDB refreshes this cache only after more than 100 ms without reads.
                usleep(150000);
                $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id=?');
                $q->execute([$waitingConnection]);

                return (int) $q->fetchColumn() > 0;
            });
            usleep(150000); // Keep the measured wait >=100 ms without depending on process scheduling.
            $streams[0]->write("RELEASE\n");
            $streams[0]->close();
            $phase = 'finalización tras RELEASE';
            $await(fn () => ! $processes[0]->isRunning() && ! $processes[1]->isRunning());
            foreach ($processes as $i => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $this->assertStringContainsString('RESULT:'.($i === 0 ? 'changed' : 'protected'), $process->getOutput());
            }
            preg_match('/LOCK_WAIT:([0-9.]+)/', $processes[1]->getOutput(), $matches);
            $this->assertGreaterThanOrEqual(100, (float) ($matches[1] ?? 0));
            $q = $pdo->prepare('SELECT COUNT(*) FROM productos WHERE codigo_interno=?');
            $q->execute([$codigo.'-NEW']);
            $this->assertSame($operations[0] === 'create' ? 1 : 0, (int) $q->fetchColumn());
            if ($operations[0] === 'edit') {
                $q = $pdo->prepare('SELECT nombre,version_bloqueo FROM productos WHERE id=?');
                $q->execute([$ids['producto']]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                $this->assertSame('Editado por 0', $row['nombre']);
                $this->assertSame(2, (int) $row['version_bloqueo']);
            }
            if (in_array('link', $operations, true)) {
                $q = $pdo->prepare('SELECT activo FROM ubicaciones WHERE id=?');
                $q->execute([$ids['ubicacion']]);
                $this->assertSame($operations[0] === 'link' ? 1 : 0, (int) $q->fetchColumn());
                $q = $pdo->prepare('SELECT COUNT(*) FROM ubicaciones_producto WHERE producto_id=?');
                $q->execute([$ids['producto']]);
                $this->assertSame($operations[0] === 'link' ? 1 : 0, (int) $q->fetchColumn());
            }
            if (in_array('required', $operations, true)) {
                $q = $pdo->prepare('SELECT obligatorio FROM atributos_categoria WHERE id=?');
                $q->execute([$ids['association']]);
                $this->assertSame($operations[0] === 'required' ? 1 : 0, (int) $q->fetchColumn());
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Remove only rows owned by this nonce, never reset the testing schema.
            $q = $pdo->prepare('SELECT id FROM productos WHERE codigo_interno IN (?,?)');
            $q->execute([$codigo.'-BASE', $codigo.'-NEW']);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $productId) {
                $q2 = $pdo->prepare('DELETE FROM ubicaciones_producto WHERE producto_id=?');
                $q2->execute([$productId]);
                $q2 = $pdo->prepare('DELETE FROM productos WHERE id=?');
                $q2->execute([$productId]);
            }
            foreach (['association' => 'atributos_categoria', 'atributo' => 'atributos', 'ubicacion' => 'ubicaciones', 'categoria' => 'categorias', 'unidad' => 'unidades_medida'] as $key => $table) {
                if (isset($ids[$key])) {
                    $q = $pdo->prepare('DELETE FROM '.$table.' WHERE id=?');
                    $q->execute([$ids[$key]]);
                }
            }
            foreach ($roles as $rol) {
                $q = $pdo->prepare('DELETE FROM registros_auditoria_administrativa WHERE rol_id=?');
                $q->execute([$rol]);
                $q = $pdo->prepare('UPDATE roles SET creado_por=NULL,modificado_por=NULL WHERE id=?');
                $q->execute([$rol]);
            }
            foreach ($usuarios as $id) {
                $q = $pdo->prepare('DELETE FROM usuarios WHERE id=?');
                $q->execute([$id]);
            }
            foreach ($roles as $rol) {
                $q = $pdo->prepare('DELETE FROM permiso_rol WHERE rol_id=?');
                $q->execute([$rol]);
                $q = $pdo->prepare('DELETE FROM roles WHERE id=?');
                $q->execute([$rol]);
            }
        }
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    }
}
