<?php

namespace Tests\Feature\Catalog;

use App\Support\TestingDatabaseGuard;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LocationConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'LOC-04 crossed parents' => ['cycle', false],
            'LOC-04 crossed parents with earlier snapshot' => ['cycle', true],
            'LOC-05 child creation versus padre deactivation' => ['create', true],
            'LOC-05 child activation versus padre deactivation' => ['activate', true],
        ];
    }

    #[DataProvider('races')]
    public function test_concurrent_mutations_keep_an_acyclic_active_hierarchy(string $mode, bool $snapshot): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = TestingDatabaseGuard::pdo();
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('REPEATABLE-READ', $pdo->query('SELECT @@tx_isolation')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $nonce = bin2hex(random_bytes(8));
        $codes = ['RACE-'.$nonce.'-A', 'RACE-'.$nonce.'-B', 'RACE-'.$nonce.'-NEW'];
        $roles = [];
        $usuarios = [];
        $processes = [];
        $streams = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $query = $pdo->prepare('INSERT INTO roles (codigo,nombre,activo,version_permisos,fecha_creacion,fecha_actualizacion) VALUES (?,?,1,1,NOW(),NOW())');
                $query->execute(['location_race_'.$nonce.'_'.$i, 'Rol de prueba']);
                $roles[] = $roleId = (int) $pdo->lastInsertId();
                $query = $pdo->prepare("INSERT INTO permiso_rol (rol_id,permiso_id,activo,fecha_creacion,fecha_actualizacion) SELECT ?,id,1,NOW(),NOW() FROM permisos WHERE codigo LIKE 'locations.%'");
                $query->execute([$roleId]);
                $this->assertSame(4, $query->rowCount(), 'La fixture necesita los cuatro permisos hist?ricos de ubicaciones.');
                $query = $pdo->prepare('INSERT INTO usuarios (nombre,correo,contrasena,rol_id,activo,version_sesion,fecha_creacion,fecha_actualizacion) VALUES (?,?,?,?,1,1,NOW(),NOW())');
                $query->execute(['Operador de prueba', $nonce.'_'.$i.'@example.test', password_hash('ClaveSoloPruebas2026!', PASSWORD_BCRYPT, ['cost' => 4]), $roleId]);
                $usuarios[] = (int) $pdo->lastInsertId();
            }
            $query = $pdo->prepare("INSERT INTO ubicaciones (codigo,nombre,tipo,padre_id,permite_almacenar,activo,fecha_creacion,fecha_actualizacion) VALUES (?,?,'sector',?,1,?,NOW(),NOW())");
            $query->execute([$codes[0], 'Raíz de prueba', null, 1]);
            $a = (int) $pdo->lastInsertId();
            $query->execute([$codes[1], 'Otra ubicación de prueba', $mode === 'activate' ? $a : null, $mode === 'activate' ? 0 : 1]);
            $b = (int) $pdo->lastInsertId();
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $nombre) {
                $env[$nombre] = $_ENV[$nombre];
            }
            foreach ($usuarios as $i => $actor) {
                $job = ['actor' => $actor, 'mode' => $mode, 'index' => $i, 'a' => $a, 'b' => $b, 'child_code' => $codes[2], 'snapshot' => $snapshot];
                $stream = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-location.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 60);
                $process->start();
                $processes[] = $process;
                $streams[] = $stream;
            }
            do {
                $ready = 0;
                foreach ($processes as $process) {
                    $process->checkTimeout();
                    if (str_contains($process->getOutput(), 'READY')) {
                        $ready++;
                    } elseif (! $process->isRunning()) {
                        $this->fail($process->getErrorOutput());
                    }
                }
                if ($ready < 2) {
                    usleep(10000);
                }
            } while ($ready < 2);
            $this->assertSame(2, $ready);
            foreach ($streams as $stream) {
                $stream->write("GO\n");
                $stream->close();
            }
            do {
                $running = false;
                foreach ($processes as $process) {
                    $process->checkTimeout();
                    $running = $process->isRunning() || $running;
                }
                if ($running) {
                    usleep(10000);
                }
            } while ($running);
            $results = [];
            $waits = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                preg_match('/RESULT:([A-Za-z_]+)/', $process->getOutput(), $matches);
                $results[] = $matches[1] ?? 'missing';
                preg_match('/LOCK_WAIT:([0-9.]+)/', $process->getOutput(), $matches);
                $waits[] = (float) ($matches[1] ?? 0);
            }
            sort($results);
            $this->assertSame(['changed', 'protected'], $results, implode("\n", array_map(fn ($p) => $p->getOutput().$p->getErrorOutput(), $processes)));
            $this->assertGreaterThanOrEqual(100, max($waits), 'Debe comprobarse la espera real del mutex.');
            $query = $pdo->prepare('SELECT id,padre_id,activo FROM ubicaciones WHERE codigo IN (?,?,?)');
            $query->execute($codes);
            $rows = $query->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
            foreach ($rows as $id => $row) {
                $seen = [$id => true];
                $parentId = $row['padre_id'];
                while ($parentId !== null) {
                    $this->assertArrayHasKey($parentId, $rows);
                    $this->assertArrayNotHasKey($parentId, $seen, 'No puede quedar un ciclo.');
                    $seen[$parentId] = true;
                    if ((int) $row['activo'] === 1) {
                        $this->assertSame(1, (int) $rows[$parentId]['activo'], 'Un hijo activo requiere ancestros activos.');
                    }
                    $parentId = $rows[$parentId]['padre_id'];
                }
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Only the three unique codes owned by this scenario may be removed.
            $query = $pdo->prepare('UPDATE ubicaciones SET padre_id=NULL WHERE codigo IN (?,?,?)');
            $query->execute($codes);
            $query = $pdo->prepare('DELETE FROM ubicaciones WHERE codigo IN (?,?,?)');
            $query->execute($codes);
            foreach ($usuarios as $id) {
                $query = $pdo->prepare('DELETE FROM usuarios WHERE id=?');
                $query->execute([$id]);
            }
            foreach ($roles as $id) {
                $query = $pdo->prepare('DELETE FROM permiso_rol WHERE rol_id=?');
                $query->execute([$id]);
                $query = $pdo->prepare('DELETE FROM roles WHERE id=?');
                $query->execute([$id]);
            }
        }
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $query = $pdo->prepare('SELECT COUNT(*) FROM ubicaciones WHERE codigo IN (?,?,?)');
        $query->execute($codes);
        $this->assertSame(0, (int) $query->fetchColumn());
    }
}
