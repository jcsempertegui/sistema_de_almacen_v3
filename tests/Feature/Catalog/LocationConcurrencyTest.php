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
            'LOC-05 child creation versus parent deactivation' => ['create', true],
            'LOC-05 child activation versus parent deactivation' => ['activate', true],
        ];
    }

    #[DataProvider('races')]
    public function test_concurrent_mutations_keep_an_acyclic_active_hierarchy(string $mode, bool $snapshot): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = TestingDatabaseGuard::pdo();
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('REPEATABLE-READ', $pdo->query('SELECT @@tx_isolation')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $nonce = bin2hex(random_bytes(8));
        $codes = ['RACE-'.$nonce.'-A', 'RACE-'.$nonce.'-B', 'RACE-'.$nonce.'-NEW'];
        $roles = [];
        $users = [];
        $processes = [];
        $streams = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $query = $pdo->prepare('INSERT INTO roles (code,name,is_active,permissions_version,created_at,updated_at) VALUES (?,?,1,1,NOW(),NOW())');
                $query->execute(['location_race_'.$nonce.'_'.$i, 'Rol de prueba']);
                $roles[] = $roleId = (int) $pdo->lastInsertId();
                $query = $pdo->prepare("INSERT INTO permission_role (role_id,permission_id,is_active,created_at,updated_at) SELECT ?,id,1,NOW(),NOW() FROM permissions WHERE code LIKE 'locations.%'");
                $query->execute([$roleId]);
                $query = $pdo->prepare('INSERT INTO users (name,email,password,role_id,is_active,session_version,created_at,updated_at) VALUES (?,?,?,?,1,1,NOW(),NOW())');
                $query->execute(['Operador de prueba', $nonce.'_'.$i.'@example.test', password_hash('ClaveSoloPruebas2026!', PASSWORD_BCRYPT, ['cost' => 4]), $roleId]);
                $users[] = (int) $pdo->lastInsertId();
            }
            $query = $pdo->prepare("INSERT INTO locations (code,name,type,parent_id,is_storable,is_active,created_at,updated_at) VALUES (?,?,'sector',?,1,?,NOW(),NOW())");
            $query->execute([$codes[0], 'Raíz de prueba', null, 1]);
            $a = (int) $pdo->lastInsertId();
            $query->execute([$codes[1], 'Otra ubicación de prueba', $mode === 'activate' ? $a : null, $mode === 'activate' ? 0 : 1]);
            $b = (int) $pdo->lastInsertId();
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $name) {
                $env[$name] = $_ENV[$name];
            }
            foreach ($users as $i => $actor) {
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
                preg_match('/RESULT:(\\w+)/', $process->getOutput(), $matches);
                $results[] = $matches[1] ?? 'missing';
                preg_match('/LOCK_WAIT:([0-9.]+)/', $process->getOutput(), $matches);
                $waits[] = (float) ($matches[1] ?? 0);
            }
            sort($results);
            $this->assertSame(['changed', 'protected'], $results);
            $this->assertGreaterThanOrEqual(100, max($waits), 'Debe comprobarse la espera real del mutex.');
            $query = $pdo->prepare('SELECT id,parent_id,is_active FROM locations WHERE code IN (?,?,?)');
            $query->execute($codes);
            $rows = $query->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);
            foreach ($rows as $id => $row) {
                $seen = [$id => true];
                $parentId = $row['parent_id'];
                while ($parentId !== null) {
                    $this->assertArrayHasKey($parentId, $rows);
                    $this->assertArrayNotHasKey($parentId, $seen, 'No puede quedar un ciclo.');
                    $seen[$parentId] = true;
                    if ((int) $row['is_active'] === 1) {
                        $this->assertSame(1, (int) $rows[$parentId]['is_active'], 'Un hijo activo requiere ancestros activos.');
                    }
                    $parentId = $rows[$parentId]['parent_id'];
                }
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Only the three unique codes owned by this scenario may be removed.
            $query = $pdo->prepare('UPDATE locations SET parent_id=NULL WHERE code IN (?,?,?)');
            $query->execute($codes);
            $query = $pdo->prepare('DELETE FROM locations WHERE code IN (?,?,?)');
            $query->execute($codes);
            foreach ($users as $id) {
                $query = $pdo->prepare('DELETE FROM users WHERE id=?');
                $query->execute([$id]);
            }
            foreach ($roles as $id) {
                $query = $pdo->prepare('DELETE FROM permission_role WHERE role_id=?');
                $query->execute([$id]);
                $query = $pdo->prepare('DELETE FROM roles WHERE id=?');
                $query->execute([$id]);
            }
        }
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $query = $pdo->prepare('SELECT COUNT(*) FROM locations WHERE code IN (?,?,?)');
        $query->execute($codes);
        $this->assertSame(0, (int) $query->fetchColumn());
    }
}
