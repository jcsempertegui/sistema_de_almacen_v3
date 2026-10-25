<?php

namespace Tests\Feature\Inventory;

use Illuminate\Support\Str;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class InventoryConcurrencyTest extends TestCase
{
    public static function races(): array
    {
        return [
            'idempotent retry' => ['same', ['confirm', 'confirm']],
            'competing issues' => ['issue', ['confirm', 'confirm']],
            'opening once' => ['opening', ['confirm', 'confirm']],
            'same individual twice' => ['individual', ['confirm', 'confirm']],
            'receipt protects individual' => ['individual', ['confirm', 'disable_unit']],
            'individual disable blocks receipt' => ['individual', ['disable_unit', 'confirm']],
            'receipt protects product' => ['receipt', ['confirm', 'disable_product']],
            'product disable blocks receipt' => ['receipt', ['disable_product', 'confirm']],
            'receipt protects pair' => ['receipt', ['confirm', 'disable_pair']],
            'pair disable blocks receipt' => ['receipt', ['disable_pair', 'confirm']],
            'revocation blocks receipt' => ['receipt', ['revoke', 'confirm']],
            'confirmation protects cancellation' => ['same', ['confirm', 'cancel']],
            'cancellation blocks confirmation' => ['same', ['cancel', 'confirm']],
        ];
    }

    #[DataProvider('races')]
    public function test_current_locked_reads_and_atomic_confirmation(string $scenario, array $operations): void
    {
        $this->assertTrue(app()->environment('testing'));
        $pdo = new PDO('mysql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'].';dbname=sga_rio_grande_testing', $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->assertSame('sga_rio_grande_testing', $pdo->query('SELECT DATABASE()')->fetchColumn());
        $this->assertSame('REPEATABLE-READ', $pdo->query('SELECT @@tx_isolation')->fetchColumn());
        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
        $nonce = bin2hex(random_bytes(7));
        $code = 'RACE-I-'.$nonce;
        $ids = [];
        $roles = [];
        $users = [];
        $documents = [];
        $processes = [];
        $streams = [];
        $insert = function (string $sql, array $values) use ($pdo): int {
            $q = $pdo->prepare($sql);
            $q->execute($values);

            return (int) $pdo->lastInsertId();
        };
        try {
            for ($i = 0; $i < 2; $i++) {
                $roles[] = $role = $insert('INSERT INTO roles (code,name,is_active,permissions_version) VALUES (?,?,1,1)', ['inventory_race_'.$nonce.'_'.$i, 'Prueba aislada']);
                $q = $pdo->prepare('INSERT INTO permission_role (role_id,permission_id,is_active) SELECT ?,id,1 FROM permissions');
                $q->execute([$role]);
                $users[] = $insert('INSERT INTO users (name,email,password,role_id,is_active,session_version) VALUES (?,?,?,?,1,1)', ['Prueba', $nonce.'_'.$i.'@example.test', password_hash('Prueba2026!', PASSWORD_BCRYPT, ['cost' => 4]), $role]);
            }
            $ids['category'] = $insert('INSERT INTO categories (code,name,is_active) VALUES (?,?,1)', [$code, $code]);
            $ids['unit'] = $insert("INSERT INTO measurement_units (code,name,symbol,dimension,decimal_places,is_active) VALUES (?,?,?,'count',0,1)", [$code, 'Unidad', $nonce]);
            $ids['location'] = $insert("INSERT INTO locations (code,name,type,is_active,is_storable) VALUES (?,?,'sector',1,1)", [$code, 'Ubicación']);
            $mode = $scenario === 'individual' ? 'individual' : 'quantity';
            $ids['product'] = $insert('INSERT INTO products (internal_code,name,category_id,measurement_unit_id,control_mode,is_active) VALUES (?,?,?,?,?,1)', [$code, 'Producto', $ids['category'], $ids['unit'], $mode]);
            $ids['pair'] = $insert('INSERT INTO product_locations (product_id,location_id,is_active) VALUES (?,?,1)', [$ids['product'], $ids['location']]);
            if ($mode === 'individual') {
                $ids['individual'] = $insert('INSERT INTO individual_units (identifier,product_id,is_active) VALUES (?,?,1)', [$code, $ids['product']]);
            }
            $makeDocument = function (string $kind, string $quantity, bool $confirmed = false) use (&$documents, $insert, $pdo, $ids, $users, $mode, $nonce): int {
                $doc = $insert("INSERT INTO inventory_documents (uuid,kind,status,document_date,cutoff_date,counterparty_id,counterparty_name,created_by,confirmed_by,confirmed_at) VALUES (?,?,?,'2026-10-19','2026-10-18','TEST','Contraparte',?,?,?)", [Str::uuid()->toString(), $kind, $confirmed ? 'confirmed' : 'draft', $users[0], $confirmed ? $users[0] : null, $confirmed ? '2026-10-19 10:00:00' : null]);
                $documents[] = $doc;
                $line = $insert('INSERT INTO inventory_document_lines (document_id,line_number,product_id,location_id,quantity,measurement_unit_id,unit_symbol,decimal_places,control_mode) VALUES (?,1,?,?,?,?,?,0,?)', [$doc, $ids['product'], $ids['location'], $quantity, $ids['unit'], $nonce, $mode]);
                if (isset($ids['individual'])) {
                    $q = $pdo->prepare('INSERT INTO inventory_document_line_units (document_line_id,document_id,product_id,individual_unit_id) VALUES (?,?,?,?)');
                    $q->execute([$line, $doc, $ids['product'], $ids['individual']]);
                }
                if ($confirmed) {
                    $q = $pdo->prepare("INSERT INTO inventory_movements (document_line_id,product_id,location_id,direction,quantity,created_by,posted_at) VALUES (?,?,?,'in',?,?,'2026-10-19 10:00:00')");
                    $q->execute([$line, $ids['product'], $ids['location'], $quantity, $users[0]]);
                }

                return $doc;
            };
            if ($scenario === 'issue') {
                $insert('INSERT INTO stock_positions (product_id,location_id,quantity) VALUES (?,?,10)', [$ids['product'], $ids['location']]);
                $makeDocument('receipt', '10', true);
            }
            $kind = $scenario === 'opening' ? 'opening' : ($scenario === 'issue' ? 'issue' : 'receipt');
            $quantity = $mode === 'individual' ? '1' : ($scenario === 'issue' ? '7' : '3');
            $first = $makeDocument($kind, $quantity);
            $second = $scenario === 'same' ? $first : $makeDocument($kind, $quantity);
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $name) {
                $env[$name] = $_ENV[$name];
            }
            foreach ($operations as $i => $operation) {
                $job = ['actor' => $users[$i], 'other_role' => $roles[1], 'document' => $i === 0 ? $first : $second, 'product' => $ids['product'], 'pair' => $ids['pair'], 'unit' => $ids['individual'] ?? null, 'operation' => $operation, 'index' => $i];
                $stream = new InputStream;
                $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-inventory.php'), json_encode($job, JSON_THROW_ON_ERROR)], base_path(), $env, $stream, 90);
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
            $await(function () use ($pdo, $waitingConnection) {
                // MariaDB refreshes this cache only after more than 100 ms without reads.
                usleep(150000);
                $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id=?');
                $q->execute([$waitingConnection]);

                return (int) $q->fetchColumn() > 0;
            });
            usleep(150000);
            $streams[0]->write("RELEASE\n");
            $streams[0]->close();
            $await(fn () => ! $processes[0]->isRunning() && ! $processes[1]->isRunning());
            foreach ($processes as $i => $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $retry = $scenario === 'same' && $operations === ['confirm', 'confirm'];
                $this->assertStringContainsString('RESULT:'.($i === 0 || $retry ? 'changed' : 'protected'), $process->getOutput());
            }
            preg_match('/LOCK_WAIT:([0-9.]+)/', $processes[1]->getOutput(), $matches);
            $this->assertGreaterThanOrEqual(100, (float) ($matches[1] ?? 0));
            $q = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM stock_positions WHERE product_id=?');
            $q->execute([$ids['product']]);
            $expected = $operations[0] === 'confirm' ? ($mode === 'individual' ? '1.000000' : '3.000000') : '0.000000';
            $this->assertSame($expected, (string) $q->fetchColumn());
            $q = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END),0) FROM inventory_movements WHERE product_id=?");
            $q->execute([$ids['product']]);
            $this->assertSame($expected, (string) $q->fetchColumn());
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            // Cleanup only fixtures created here, after verifying the dedicated database.
            if (isset($ids['product'])) {
                foreach (['individual_unit_positions', 'inventory_movements', 'initial_stock_products', 'inventory_document_line_units', 'inventory_document_lines', 'stock_positions', 'individual_units', 'product_locations'] as $table) {
                    $q = $pdo->prepare('DELETE FROM '.$table.' WHERE product_id=?');
                    $q->execute([$ids['product']]);
                }
                $q = $pdo->prepare('DELETE FROM products WHERE id=?');
                $q->execute([$ids['product']]);
            }
            foreach ($documents as $document) {
                $q = $pdo->prepare('DELETE FROM inventory_documents WHERE id=?');
                $q->execute([$document]);
            }
            foreach (['location' => 'locations', 'category' => 'categories', 'unit' => 'measurement_units'] as $key => $table) {
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
            foreach ($users as $user) {
                $q = $pdo->prepare('DELETE FROM users WHERE id=?');
                $q->execute([$user]);
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
