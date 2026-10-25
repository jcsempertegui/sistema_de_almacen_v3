<?php

namespace Tests\Feature\Inventory;

use App\Actions\Deliveries\SaveDelivery;
use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\SaveReturn;
use App\Models\IndividualUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Services\ReturnRegularization;
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
        return ['competing deliveries' => ['delivery'], 'exclusive custody' => ['loan'], 'loan retry' => ['loan_retry'], 'competing returns' => ['return'], 'return retry' => ['return_retry'], 'permission revocation' => ['revoke'], 'worker deactivation' => ['worker'], 'authorization revoked first' => ['regularization'], 'return protects used approval' => ['regularization_reverse'], 'inactive identity blocks ordinary return' => ['return_disable_first'], 'received identity blocks deactivation' => ['return_disable_last']];
    }

    #[DataProvider('races')]
    public function test_atomic_current_state_under_real_sql_wait(string $scenario): void
    {
        $this->assertTrue(app()->environment('testing'));
        $this->assertSame('sga_rio_grande_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('users')->count());
        $c = null;
        $other = null;
        $role = null;
        $worker = null;
        $processes = [];
        $streams = [];
        try {
            $c = $this->inventoryContext($scenario === 'delivery' ? 'quantity' : 'individual');
            $role = Role::create(['code' => 's4_race_'.bin2hex(random_bytes(7)), 'name' => 'Prueba aislada', 'is_active' => true]);
            foreach (Permission::pluck('id') as $id) {
                DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $id, 'is_active' => true]);
            }
            $other = User::factory()->create(['role_id' => $role->id]);
            $worker = Worker::factory()->create();
            $kind = $scenario === 'delivery' ? 'deliveries' : 'loans';
            $operation = ['confirm', 'confirm'];
            $authorization = null;
            if ($kind === 'deliveries') {
                $this->postInventory($c, 'receipt', '10');
                $data = ['worker_id' => $worker->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'quantity' => '7', 'unit_ids' => []]]];
                $a = app(SaveDelivery::class)->handle($c['actor'], null, $data);
                $b = app(SaveDelivery::class)->handle($other, null, $data);
            } else {
                $c['product']->update(['is_loanable' => true]);
                if (in_array($scenario, ['loan', 'loan_retry'], true)) {
                    $extra = IndividualUnit::factory()->create(['product_id' => $c['product']->id, 'location_id' => null]);
                    $receiptData = $this->inventoryData($c, 'receipt', '2');
                    $receiptData['lines'][0]['unit_ids'][] = $extra->id;
                    $receipt = app(SaveInventoryDocument::class)->handle($c['actor'], null, $receiptData);
                    app(ConfirmInventoryDocument::class)->handle($c['actor'], $receipt, 1);
                } else {
                    $this->postInventory($c, 'receipt', '1');
                }
                $data = ['worker_id' => $worker->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]];
                $a = app(SaveLoan::class)->handle($c['actor'], null, $data);
                $b = $scenario === 'loan_retry' ? $a : app(SaveLoan::class)->handle($other, null, $data);
                if (str_starts_with($scenario, 'return') || str_starts_with($scenario, 'regularization')) {
                    $loan = app(ConfirmLoan::class)->handle($c['actor'], $a, 1);
                    $kind = 'returns';
                    if (str_starts_with($scenario, 'regularization')) {
                        DB::table('individual_units')->where('id', $c['unit']->id)->update(['is_active' => false]);
                    }
                    $data = ['loan_id' => $loan->id, 'document_date' => '2026-10-19', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]];
                    $a = app(SaveReturn::class)->handle($c['actor'], null, $data);
                    $b = $scenario === 'return' ? app(SaveReturn::class)->handle($other, null, $data) : $a;
                    if ($scenario === 'return_disable_first') {
                        $operation = ['disable_unit', 'confirm'];
                    }if ($scenario === 'return_disable_last') {
                        $operation = ['confirm', 'disable_unit'];
                    }
                    if (str_starts_with($scenario, 'regularization')) {
                        $authorization = app(ReturnRegularization::class)->request($c['actor'], $a->lines()->first(), 1, 'Prueba concurrente');
                        $authorization = app(ReturnRegularization::class)->decide($c['actor'], $authorization, true, 'Identidad revisada');
                        $operation = $scenario === 'regularization' ? ['revoke_regularization', 'confirm'] : ['confirm', 'revoke_regularization'];
                    }
                }
                if ($scenario === 'revoke') {
                    $operation = ['revoke', 'confirm'];
                }if ($scenario === 'worker') {
                    $operation = ['disable_worker', 'confirm'];
                }
            }
            $env = ['APP_ENV' => 'testing', 'APP_KEY' => $_ENV['APP_KEY'], 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'];
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
                $env[$key] = $_ENV[$key];
            }
            foreach ([$a, $b] as $index => $doc) {
                $stream = new InputStream;
                $job = ['actor' => $index ? $other->id : $c['actor']->id, 'document' => $doc->id, 'kind' => $kind, 'operation' => $operation[$index], 'index' => $index, 'role' => $role->id, 'authorization' => $authorization?->id];
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
            $balance = (string) DB::table('stock_positions')->where('product_id', $c['product']->id)->sum('quantity');
            $ledger = (string) DB::table('inventory_movements')->where('product_id', $c['product']->id)->selectRaw("SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) AS total")->first()->total;
            $this->assertSame($balance, $ledger);
            $expectedBalance = $scenario === 'delivery' ? '3.000000' : (in_array($scenario, ['loan', 'loan_retry', 'return', 'return_retry', 'regularization_reverse', 'return_disable_last', 'revoke', 'worker'], true) ? '1.000000' : '0.000000');
            $this->assertSame($expectedBalance, $balance);
            $this->assertSame(in_array($scenario, ['return', 'return_retry', 'regularization_reverse', 'return_disable_last'], true) ? 1 : 0, DB::table('loan_item_settlements')->count());
        } finally {
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop();
                }
            }
            if ($c) {
                $product = $c['product']->id;
                $returnItems = DB::table('tool_return_items')->where('product_id', $product)->pluck('id');
                $auth = DB::table('return_regularization_authorizations')->whereIn('return_item_id', $returnItems)->pluck('id');
                // Remove only cycles created by this committed race fixture.
                $cycles = DB::table('loan_custody_cycles')->where('product_id', $product)->orderByDesc('id')->pluck('id');
                DB::table('individual_unit_custodies')->where('product_id', $product)->delete();
                DB::table('tool_return_items')->whereIn('id', $returnItems)->update(['custody_cycle_id' => null]);
                DB::table('loan_cycle_closures')->whereIn('cycle_id', $cycles)->delete();
                foreach ($cycles as $cycleId) {
                    DB::table('loan_custody_cycles')->where('id', $cycleId)->delete();
                }
                DB::table('individual_unit_positions')->where('product_id', $product)->delete();
                DB::table('loan_item_settlements')->whereIn('return_item_id', $returnItems)->delete();
                DB::table('individual_unit_custodies')->where('product_id', $product)->delete();
                DB::table('return_regularization_events')->whereIn('authorization_id', $auth)->delete();
                DB::table('return_regularization_authorizations')->whereIn('id', $auth)->delete();
                DB::table('inventory_movements')->where('product_id', $product)->delete();
                foreach (['tool_return_items', 'tool_loan_items', 'material_delivery_line_units', 'material_delivery_lines', 'inventory_document_line_units', 'inventory_document_lines', 'initial_stock_products', 'stock_positions', 'individual_units', 'product_locations'] as $table) {
                    DB::table($table)->where('product_id', $product)->delete();
                }
                foreach (['tool_returns', 'tool_loans', 'material_deliveries', 'inventory_documents'] as $table) {
                    DB::table($table)->whereIn('created_by', array_filter([$c['actor']->id, $other?->id]))->delete();
                }
                DB::table('products')->where('id', $product)->delete();
                DB::table('locations')->where('id', $c['location']->id)->delete();
                DB::table('categories')->where('id', $c['product']->category_id)->delete();
                DB::table('measurement_units')->where('id', $c['product']->measurement_unit_id)->delete();
            }
            if ($worker) {
                DB::table('workers')->where('id', $worker->id)->delete();
            }if ($role) {
                DB::table('administrative_audit_logs')->where('role_id', $role->id)->delete();
                DB::table('roles')->where('id', $role->id)->update(['created_by' => null, 'updated_by' => null]);
            }
            DB::table('users')->whereIn('id', array_filter([$c ? $c['actor']->id : null, $other?->id]))->delete();
            if ($role) {
                DB::table('permission_role')->where('role_id', $role->id)->delete();
                DB::table('roles')->where('id', $role->id)->delete();
            }
        }
        $this->assertSame(0, DB::table('users')->count());
    }
}
