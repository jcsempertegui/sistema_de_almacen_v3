<?php

namespace Tests\Feature;

use App\Support\TestingDatabaseGuard;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class AdministrativeConcurrencyTest extends TestCase
{
    public function test_simultaneous_deactivation_keeps_one_full_administrator(): void
    {
        $this->race('usuario');
    }

    public function test_simultaneous_role_revocation_keeps_one_full_administrator(): void
    {
        $this->race('rol');
    }

    private function race(string $mode): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = TestingDatabaseGuard::pdo();
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        // Independent processes require committed fixtures, invisible inside
        // the default transaction. Remove ONLY IDs created by this test.
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
        $roles = [];
        $usuarios = [];
        $processes = [];
        $streams = [];
        try {
            $nonce = bin2hex(random_bytes(8));
            for ($i = 0; $i < 2; $i++) {
                $statement = $pdo->prepare('INSERT INTO roles (codigo, nombre, activo, version_permisos, fecha_creacion, fecha_actualizacion) VALUES (?, ?, 1, 1, NOW(), NOW())');
                $statement->execute(['race_'.$nonce.'_'.$i, 'Prueba concurrente']);
                $roles[] = $roleId = (int) $pdo->lastInsertId();
                $statement = $pdo->prepare('INSERT INTO permiso_rol (rol_id, permiso_id, activo, fecha_creacion, fecha_actualizacion) SELECT ?, id, 1, NOW(), NOW() FROM permisos');
                $statement->execute([$roleId]);
                $statement = $pdo->prepare('INSERT INTO usuarios (nombre, correo, contrasena, rol_id, activo, version_sesion, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())');
                $statement->execute(['Cuenta de prueba', $nonce.'_'.$i.'@example.test', password_hash('ClaveSoloPruebas2026!', PASSWORD_BCRYPT, ['cost' => 4]), $roleId]);
                $usuarios[] = (int) $pdo->lastInsertId();
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            $env = array_replace($env, TestingDatabaseGuard::processEnvironment());
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $nombre) {
                $env[$nombre] = $_ENV[$nombre];
            }
            foreach ($usuarios as $id) {
                $stream = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-administration.php'), (string) $id, $mode], base_path(), $env, $stream, 30);
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
            // Pump both inputs and outputs while both processes contend.
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
                preg_match('/RESULT:(\w+)/', $process->getOutput(), $matches);
                $results[] = $matches[1] ?? 'missing';
                preg_match('/LOCK_WAIT:([0-9.]+)/', $process->getOutput(), $matches);
                $waits[] = (float) ($matches[1] ?? 0);
            }
            sort($results);
            $this->assertSame(['changed', 'protected'], $results);
            $this->assertGreaterThanOrEqual(100, max($waits), 'Una conexión debe esperar el bloqueo de la otra.');
            if ($mode === 'usuario') {
                $this->assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn());
            } else {
                $statement = $pdo->prepare("SELECT COUNT(*) FROM permiso_rol pr JOIN permisos p ON p.id = pr.permiso_id WHERE pr.rol_id IN (?, ?) AND pr.activo = 1 AND p.codigo = 'users.update'");
                $statement->execute($roles);
                $this->assertSame(1, (int) $statement->fetchColumn());
            }
            $statement = $pdo->prepare('SELECT COUNT(*) FROM registros_auditoria_administrativa WHERE actor_id IN (?, ?)');
            $statement->execute($usuarios);
            $this->assertSame(1, (int) $statement->fetchColumn());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach ($roles as $roleId) {
                $statement = $pdo->prepare('UPDATE roles SET creado_por = NULL, modificado_por = NULL WHERE id = ?');
                $statement->execute([$roleId]);
                $statement = $pdo->prepare('DELETE FROM registros_auditoria_administrativa WHERE rol_id = ? OR usuario_id IN (SELECT id FROM usuarios WHERE rol_id = ?) OR actor_id IN (SELECT id FROM usuarios WHERE rol_id = ?)');
                $statement->execute([$roleId, $roleId, $roleId]);
                $statement = $pdo->prepare('DELETE FROM usuarios WHERE rol_id = ?');
                $statement->execute([$roleId]);
                $statement = $pdo->prepare('DELETE FROM permiso_rol WHERE rol_id = ?');
                $statement->execute([$roleId]);
                $statement = $pdo->prepare('DELETE FROM roles WHERE id = ?');
                $statement->execute([$roleId]);
            }
        }
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    }
}
