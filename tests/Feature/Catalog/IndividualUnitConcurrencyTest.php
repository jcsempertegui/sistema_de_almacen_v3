<?php

namespace Tests\Feature\Catalog;

use App\Support\TestingDatabaseGuard;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class IndividualUnitConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'identificador collision' => [['create', 'create'], ['changed', 'protected'], false],
            'serial collision' => [['create', 'create'], ['changed', 'protected'], true],
            'stale edits' => [['edit', 'edit'], ['changed', 'protected'], false],
            'disable producto first' => [['disable_product', 'create'], ['changed', 'protected'], false],
            'create before producto disable' => [['create', 'disable_product'], ['changed', 'protected'], false],
            'disable link first' => [['disable_link', 'create'], ['changed', 'protected'], false],
            'create before link disable' => [['create', 'disable_link'], ['changed', 'protected'], false],
            'disable ubicacion first' => [['disable_location', 'create'], ['protected', 'changed'], false],
            'create before ubicacion disable' => [['create', 'disable_location'], ['changed', 'protected'], false],
            'revoke first' => [['revoke', 'create'], ['changed', 'protected'], false],
            'create before revoke' => [['create', 'revoke'], ['changed', 'changed'], false],
        ];
    }

    #[DataProvider('races')]
    public function test_writers_use_current_references_and_permissions_after_waiting(array $operations, array $expected, bool $sameSerial): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = TestingDatabaseGuard::pdo();
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('REPEATABLE-READ', $pdo->query('SELECT @@tx_isolation')->fetchColumn());
        $nonce = bin2hex(random_bytes(7));
        $codigo = 'RACE-I-'.strtoupper($nonce);
        $ids = $roles = $usuarios = $processes = $streams = [];
        $insert = function (string $sql, array $values) use ($pdo): int {
            $q = $pdo->prepare($sql);
            $q->execute($values);

            return (int) $pdo->lastInsertId();
        };
        try {
            for ($i = 0; $i < 2; $i++) {
                $roles[] = $rol = $insert('INSERT INTO roles (codigo,nombre,activo,version_permisos) VALUES (?,?,1,1)', ['individual_race_'.$nonce.'_'.$i, 'Prueba']);
                $q = $pdo->prepare('INSERT INTO permiso_rol (rol_id,permiso_id,activo) SELECT ?,id,1 FROM permisos');
                $q->execute([$rol]);
                $usuarios[] = $insert('INSERT INTO usuarios (nombre,correo,contrasena,rol_id,activo,version_sesion) VALUES (?,?,?,?,1,1)', ['Prueba', $nonce.'_'.$i.'@example.test', password_hash('Prueba2026!', PASSWORD_BCRYPT, ['cost' => 4]), $rol]);
            }
            $ids['categoria'] = $insert('INSERT INTO categorias (codigo,nombre,activo) VALUES (?,?,1)', [$codigo, $codigo]);
            $ids['measurement'] = $insert("INSERT INTO unidades_medida (codigo,nombre,simbolo,dimension,decimales,activo) VALUES (?,?,?,'count',0,1)", [$codigo, 'Unidad', $nonce]);
            $ids['ubicacion'] = $insert("INSERT INTO ubicaciones (codigo,nombre,tipo,activo,permite_almacenar) VALUES (?,?,'sector',1,1)", [$codigo, 'Ubicación']);
            $ids['producto'] = $insert("INSERT INTO productos (codigo_interno,nombre,categoria_id,unidad_medida_id,modo_control,activo) VALUES (?,?,?,?,'individual',1)", [$codigo, 'Base', $ids['categoria'], $ids['measurement']]);
            $ids['link'] = $insert('INSERT INTO ubicaciones_producto (producto_id,ubicacion_id,activo) VALUES (?,?,1)', [$ids['producto'], $ids['ubicacion']]);
            $ids['individual'] = null;
            if (in_array('edit', $operations, true)) {
                $ids['individual'] = $insert('INSERT INTO unidades_individuales (identificador,producto_id,ubicacion_id,activo) VALUES (?,?,?,1)', [$codigo.'-BASE', $ids['producto'], $ids['ubicacion']]);
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $nombre) {
                $env[$nombre] = $_ENV[$nombre];
            }
            foreach ($operations as $i => $operation) {
                $job = $ids + ['actor' => $usuarios[$i], 'index' => $i, 'operation' => $operation, 'other_role' => $roles[1 - $i], 'identificador' => $codigo.($sameSerial ? '-'.$i : '-NEW'), 'serial' => $sameSerial ? 'SERIAL-'.$nonce : null];
                $stream = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-individual-unit.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 120);
                $process->start();
                $processes[] = $process;
                $streams[] = $stream;
            }
            $await = function (callable $ready) use (&$processes): void {
                do {
                    foreach ($processes as $process) {
                        $process->checkTimeout();
                        if (! $process->isRunning() && ! str_contains($process->getOutput(), 'RESULT:')) {
                            $this->fail($process->getErrorOutput().$process->getOutput());
                        }
                    }
                    if ($ready()) {
                        return;
                    }
                    usleep(10000);
                } while (true);
            };
            $await(fn () => count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'READY'))) === 2);
            $streams[0]->write("GO\n");
            $await(fn () => str_contains($processes[0]->getOutput(), 'LOCKED'));
            $streams[1]->write("GO\n");
            $streams[1]->close();
            preg_match('/CONNECTION_ID:([0-9]+)/', $processes[1]->getOutput(), $connectionMatch);
            $waitingConnection = (int) ($connectionMatch[1] ?? 0);
            $this->assertGreaterThan(0, $waitingConnection);
            // Release the winner only AFTER MariaDB confirms the second connection is blocked.
            $await(function () use ($pdo, $waitingConnection) {
                // MariaDB refreshes this cache only after more than 100 ms without reads.
                usleep(150000);
                $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id=?');
                $q->execute([$waitingConnection]);

                return (int) $q->fetchColumn() > 0;
            });
            usleep(150000); // Keep the measured wait >=100 ms without depending on process scheduling.
            $streams[0]->write("RELEASE\n");
            $streams[0]->close();
            $await(fn () => ! $processes[0]->isRunning() && ! $processes[1]->isRunning());
            foreach ($processes as $i => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $this->assertStringContainsString('RESULT:'.$expected[$i], $process->getOutput());
            }
            preg_match('/LOCK_WAIT:([0-9.]+)/', $processes[1]->getOutput(), $matches);
            $this->assertGreaterThanOrEqual(100, (float) ($matches[1] ?? 0));
            $created = 0;
            foreach ($operations as $i => $operation) {
                $created += (int) ($operation === 'create' && $expected[$i] === 'changed');
            }
            $q = $pdo->prepare('SELECT COUNT(*) FROM unidades_individuales WHERE producto_id=?');
            $q->execute([$ids['producto']]);
            $this->assertSame($created + (int) ($ids['individual'] !== null), (int) $q->fetchColumn());
            if ($ids['individual']) {
                $q = $pdo->prepare('SELECT observaciones,version_bloqueo FROM unidades_individuales WHERE id=?');
                $q->execute([$ids['individual']]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                $this->assertSame('Editado por 0', $row['observaciones']);
                $this->assertSame(2, (int) $row['version_bloqueo']);
            }
            foreach (['disable_product' => ['productos', $ids['producto']], 'disable_link' => ['ubicaciones_producto', $ids['link']], 'disable_location' => ['ubicaciones', $ids['ubicacion']]] as $op => [$table, $id]) {
                if (in_array($op, $operations, true)) {
                    $q = $pdo->prepare('SELECT activo FROM '.$table.' WHERE id=?');
                    $q->execute([$id]);
                    $this->assertSame($expected[array_search($op, $operations, true)] === 'changed' ? 0 : 1, (int) $q->fetchColumn());
                }
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Only fixtures owned by this nonce, in the verified testing database.
            if (isset($ids['producto'])) {
                foreach (['unidades_individuales', 'ubicaciones_producto'] as $table) {
                    $q = $pdo->prepare('DELETE FROM '.$table.' WHERE producto_id=?');
                    $q->execute([$ids['producto']]);
                }
            }
            foreach (['producto' => 'productos', 'ubicacion' => 'ubicaciones', 'categoria' => 'categorias', 'measurement' => 'unidades_medida'] as $key => $table) {
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
                foreach (['permiso_rol' => 'rol_id', 'roles' => 'id'] as $table => $key) {
                    $q = $pdo->prepare('DELETE FROM '.$table.' WHERE '.$key.'=?');
                    $q->execute([$rol]);
                }
            }
        }
    }
}
