<?php

namespace Tests\Feature\Catalog;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class ProductConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return ['same code' => [['create', 'create']], 'stale edit' => [['edit', 'edit']], 'disable versus link' => [['disable', 'link']], 'link versus disable' => [['link', 'disable']], 'required versus product' => [['required', 'create']], 'product versus required' => [['create', 'required']], 'revocation versus product' => [['revoke', 'create']]];
    }

    #[DataProvider('races')]
    public function test_concurrent_writers_revalidate_current_state_after_the_mutex(array $operations): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = new PDO('mysql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'].';dbname=sga_rio_grande_testing', $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('REPEATABLE-READ', $pdo->query('SELECT @@tx_isolation')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $nonce = bin2hex(random_bytes(7));
        $code = 'RACE-P-'.$nonce;
        $ids = [];
        $roles = [];
        $users = [];
        $processes = [];
        $streams = [];
        $insert = function (string $sql, array $values) use ($pdo): int {
            $q = $pdo->prepare($sql);
            $q->execute($values);

            return (int) $pdo->lastInsertId();
        };
        try {
            for ($i = 0; $i < 2; $i++) {
                $roles[] = $role = $insert('INSERT INTO roles (code,name,is_active,permissions_version) VALUES (?,?,1,1)', ['product_race_'.$nonce.'_'.$i, 'Prueba']);
                $q = $pdo->prepare('INSERT INTO permission_role (role_id,permission_id,is_active) SELECT ?,id,1 FROM permissions');
                $q->execute([$role]);
                $users[] = $insert('INSERT INTO users (name,email,password,role_id,is_active,session_version) VALUES (?,?,?,?,1,1)', ['Prueba', $nonce.'_'.$i.'@example.test', password_hash('Prueba2026!', PASSWORD_BCRYPT, ['cost' => 4]), $role]);
            }
            $ids['category'] = $insert('INSERT INTO categories (code,name,is_active) VALUES (?,?,1)', [$code, 'Categoría concurrente']);
            $ids['unit'] = $insert("INSERT INTO measurement_units (code,name,symbol,dimension,decimal_places,is_active) VALUES (?,?,?,'count',0,1)", [$code, 'Unidad', $nonce]);
            $ids['location'] = $insert("INSERT INTO locations (code,name,type,is_active,is_storable) VALUES (?,?,'sector',1,1)", [$code, 'Ubicación']);
            $ids['attribute'] = $insert("INSERT INTO attributes (code,name,data_type,is_active) VALUES (?,?,'text',1)", [$code, 'Identificador']);
            $ids['association'] = $insert('INSERT INTO category_attributes (category_id,attribute_id,is_required,is_active) VALUES (?,?,0,1)', [$ids['category'], $ids['attribute']]);
            $ids['product'] = null;
            if (in_array('edit', $operations, true) || in_array('link', $operations, true)) {
                $ids['product'] = $insert("INSERT INTO products (internal_code,name,category_id,measurement_unit_id,control_mode,is_active) VALUES (?,?,?,?,'quantity',1)", [$code.'-BASE', 'Base', $ids['category'], $ids['unit']]);
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $name) {
                $env[$name] = $_ENV[$name];
            }
            foreach ($operations as $i => $operation) {
                $job = $ids + ['actor' => $users[$i], 'index' => $i, 'operation' => $operation, 'other_role' => $roles[1], 'code' => $code.'-NEW'];
                $stream = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-product.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 120);
                $process->start();
                $processes[] = $process;
                $streams[] = $stream;
            }
            $await = function (callable $ready) use (&$processes): void {
                do {
                    foreach ($processes as $process) {
                        $process->checkTimeout();
                        if (! $process->isRunning() && ! str_contains($process->getOutput(), 'RESULT:')) {
                            $this->fail($process->getErrorOutput());
                        }
                    }
                    if ($ready()) {
                        return;
                    } usleep(10000);
                } while (true);
            };
            $await(fn () => count(array_filter($processes, fn ($p) => str_contains($p->getOutput(), 'READY'))) === 2);
            $streams[0]->write("GO\n");
            $streams[0]->close();
            $await(fn () => str_contains($processes[0]->getOutput(), 'LOCKED'));
            $streams[1]->write("GO\n");
            $streams[1]->close();
            $await(fn () => ! $processes[0]->isRunning() && ! $processes[1]->isRunning());
            foreach ($processes as $i => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $this->assertStringContainsString('RESULT:'.($i === 0 ? 'changed' : 'protected'), $process->getOutput());
            }
            preg_match('/LOCK_WAIT:([0-9.]+)/', $processes[1]->getOutput(), $matches);
            $this->assertGreaterThanOrEqual(100, (float) ($matches[1] ?? 0));
            $q = $pdo->prepare('SELECT COUNT(*) FROM products WHERE internal_code=?');
            $q->execute([$code.'-NEW']);
            $this->assertSame($operations[0] === 'create' ? 1 : 0, (int) $q->fetchColumn());
            if ($operations[0] === 'edit') {
                $q = $pdo->prepare('SELECT name,lock_version FROM products WHERE id=?');
                $q->execute([$ids['product']]);
                $row = $q->fetch(PDO::FETCH_ASSOC);
                $this->assertSame('Editado por 0', $row['name']);
                $this->assertSame(2, (int) $row['lock_version']);
            }
            if (in_array('link', $operations, true)) {
                $q = $pdo->prepare('SELECT is_active FROM locations WHERE id=?');
                $q->execute([$ids['location']]);
                $this->assertSame($operations[0] === 'link' ? 1 : 0, (int) $q->fetchColumn());
                $q = $pdo->prepare('SELECT COUNT(*) FROM product_locations WHERE product_id=?');
                $q->execute([$ids['product']]);
                $this->assertSame($operations[0] === 'link' ? 1 : 0, (int) $q->fetchColumn());
            }
            if (in_array('required', $operations, true)) {
                $q = $pdo->prepare('SELECT is_required FROM category_attributes WHERE id=?');
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
            $q = $pdo->prepare('SELECT id FROM products WHERE internal_code IN (?,?)');
            $q->execute([$code.'-BASE', $code.'-NEW']);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $productId) {
                $q2 = $pdo->prepare('DELETE FROM product_locations WHERE product_id=?');
                $q2->execute([$productId]);
                $q2 = $pdo->prepare('DELETE FROM products WHERE id=?');
                $q2->execute([$productId]);
            }
            foreach (['association' => 'category_attributes', 'attribute' => 'attributes', 'location' => 'locations', 'category' => 'categories', 'unit' => 'measurement_units'] as $key => $table) {
                if (isset($ids[$key])) {
                    $q = $pdo->prepare('DELETE FROM '.$table.' WHERE id=?');
                    $q->execute([$ids[$key]]);
                }
            }
            foreach ($roles as $role) {
                $q = $pdo->prepare('DELETE FROM administrative_audit_logs WHERE role_id=?');
                $q->execute([$role]);
                $q = $pdo->prepare('UPDATE roles SET created_by=NULL,updated_by=NULL WHERE id=?');
                $q->execute([$role]);
            }
            foreach ($users as $id) {
                $q = $pdo->prepare('DELETE FROM users WHERE id=?');
                $q->execute([$id]);
            }
            foreach ($roles as $role) {
                $q = $pdo->prepare('DELETE FROM permission_role WHERE role_id=?');
                $q->execute([$role]);
                $q = $pdo->prepare('DELETE FROM roles WHERE id=?');
                $q->execute([$role]);
            }
        }
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
    }
}
