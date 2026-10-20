<?php

namespace Tests\Feature\Inventory;

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\Worker;
use App\Services\InventoryCorrections;
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
        return ['competing counts' => ['adjustment_race'], 'idempotent retry' => ['adjustment_retry'], 'duplicate compensation' => ['compensation_race'], 'revocation first' => ['auth_revoke_first'], 'execution first' => ['execute_before_revoke'], 'permission revoked' => ['role_revoke_first'], 'authorizer role disabled' => ['authorizer_role_disabled'], 'return compensation first' => ['compensation_before_loan'], 'new loan first' => ['loan_before_compensation']];
    }

    #[DataProvider('races')]
    public function test_real_wait_revalidates_balance_authorization_and_custody(string $scenario): void
    {
        $this->assertSame('sga_rio_grande_testing', DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseCount('users', 0);
        $c = null;
        $other = null;
        $role = null;
        $worker = null;
        $processes = [];
        $streams = [];
        try {
            $individual = in_array($scenario, ['compensation_before_loan', 'loan_before_compensation']);
            $c = $this->inventoryContext($individual ? 'individual' : 'quantity');
            $role = Role::create(['code' => 's5_race_'.bin2hex(random_bytes(7)), 'name' => 'Prueba aislada', 'is_active' => true]);
            foreach (Permission::pluck('id') as $id) {
                DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $id, 'is_active' => true]);
            }$other = User::factory()->create(['role_id' => $role->id]);
            $svc = app(InventoryCorrections::class);
            $authorization = null;
            $sourceKind = 'inventory-documents';
            if ($individual) {
                $c['product']->update(['is_loanable' => true]);
                $receipt = $this->postInventory($c, 'receipt', '1');
                $worker = Worker::factory()->create();
                $loanData = ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]];
                $loan = app(SaveLoan::class)->handle($c['actor'], null, $loanData);
                $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
                $ret = app(SaveReturn::class)->handle($c['actor'], null, ['loan_id' => $loan->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]]);
                $ret = app(ConfirmReturn::class)->handle($c['actor'], $ret, 1);
                $sourceKind = 'returns';
                $sourceId = $ret->id;
                $authorization = app(SaveLoan::class)->handle($other, null, $loanData);
            } else {
                $receipt = $this->postInventory($c, 'receipt', '3');
                $sourceId = $receipt->id;
            }
            $make = function () use ($svc, $c, $other, $scenario, $individual, $sourceKind, $sourceId) {
                $data = ['reason' => 'Carrera aislada', 'document_date' => '2026-10-20'];
                if ($scenario === 'compensation_race' || $individual) {
                    $data += ['kind' => 'compensation', 'source_kind' => $sourceKind, 'source_id' => $sourceId];
                } else {
                    $data += ['kind' => 'adjustment', 'expected_line_count' => 1, 'lines' => [['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'counted_quantity' => '0', 'unit_ids' => []]]];
                }$doc = $svc->save($c['actor'], null, $data);
                $svc->transition($c['actor'], $doc, 'request', 1);

                return $svc->transition($other, $doc, 'approve', 1, 'Revisión independiente');
            };
            $a = $make();
            $b = in_array($scenario, ['adjustment_race', 'compensation_race']) ? $make() : $a;
            $kind = 'corrections';
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
            foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
                $env[$key] = $_ENV[$key];
            }
            foreach ([$a, $b] as $index => $doc) {
                $stream = new InputStream;
                $job = ['actor' => $index && $scenario !== 'authorizer_role_disabled' ? $other->id : $c['actor']->id, 'document' => $doc->id, 'kind' => $kind, 'operation' => $operation[$index], 'index' => $index, 'role' => $role->id, 'authorization' => $authorization?->id];
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
            $balance = (string) DB::table('stock_positions')->where('product_id', $c['product']->id)->sum('quantity');
            $ledger = (string) DB::table('inventory_movements')->where('product_id', $c['product']->id)->selectRaw("SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) AS total")->first()->total;
            $this->assertSame($balance, $ledger);

            $expectedBalance = in_array($scenario, ['auth_revoke_first', 'role_revoke_first', 'authorizer_role_disabled']) ? '3.000000' : '0.000000';
            $this->assertSame($expectedBalance, $balance);
            if ($individual) {
                $this->assertSame(1, DB::table('individual_unit_custodies')->where('product_id', $c['product']->id)->whereNotNull('current_cycle_id')->count());
            }
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            if ($c) {
                $product = $c['product']->id;
                $actors = array_filter([$c['actor']->id, $other?->id]);
                $docs = DB::table('inventory_corrections')->whereIn('created_by', $actors)->pluck('id');
                $lines = DB::table('inventory_correction_lines')->whereIn('correction_id', $docs)->pluck('id');
                $auths = DB::table('correction_authorizations')->whereIn('correction_id', $docs)->pluck('id');
                DB::table('individual_unit_positions')->where('product_id', $product)->delete();
                DB::table('individual_unit_custodies')->where('product_id', $product)->delete();
                DB::table('tool_return_items')->where('product_id', $product)->update(['custody_cycle_id' => null]);
                $cycles = DB::table('loan_custody_cycles')->where('product_id', $product)->orderByDesc('id')->pluck('id');
                foreach ($cycles as $id) {
                    DB::table('loan_cycle_closures')->where('cycle_id', $id)->delete();
                    DB::table('loan_custody_cycles')->where('id', $id)->delete();
                }
                $returnItems = DB::table('tool_return_items')->where('product_id', $product)->pluck('id');
                DB::table('loan_item_settlements')->whereIn('return_item_id', $returnItems)->delete();
                DB::table('document_compensations')->whereIn('correction_id', $docs)->delete();
                DB::table('inventory_movements')->whereIn('correction_line_id', $lines)->delete();
                DB::table('inventory_correction_line_units')->whereIn('correction_line_id', $lines)->delete();
                DB::table('inventory_correction_lines')->whereIn('id', $lines)->delete();
                DB::table('correction_authorization_events')->whereIn('authorization_id', $auths)->delete();
                DB::table('correction_authorizations')->whereIn('id', $auths)->delete();
                DB::table('critical_operation_events')->whereIn('correction_id', $docs)->delete();
                DB::table('inventory_corrections')->whereIn('id', $docs)->delete();
                DB::table('inventory_movements')->where('product_id', $product)->delete();
                foreach (['tool_return_items', 'tool_loan_items', 'inventory_document_line_units', 'inventory_document_lines', 'stock_positions', 'individual_units', 'product_locations'] as $table) {
                    DB::table($table)->where('product_id', $product)->delete();
                }foreach (['tool_returns', 'tool_loans', 'inventory_documents'] as $table) {
                    DB::table($table)->whereIn('created_by', $actors)->delete();
                }DB::table('products')->where('id', $product)->delete();
                DB::table('locations')->where('id', $c['location']->id)->delete();
                DB::table('categories')->where('id', $c['product']->category_id)->delete();
                DB::table('measurement_units')->where('id', $c['product']->measurement_unit_id)->delete();
                if ($worker) {
                    DB::table('workers')->where('id', $worker->id)->delete();
                }if ($role) {
                    DB::table('administrative_audit_logs')->where('role_id', $role->id)->delete();
                    DB::table('roles')->where('id', $role->id)->update(['created_by' => null, 'updated_by' => null]);
                }DB::table('users')->whereIn('id', $actors)->delete();
                if ($role) {
                    DB::table('permission_role')->where('role_id', $role->id)->delete();
                    DB::table('roles')->where('id', $role->id)->delete();
                }
            }
        }
        $this->assertDatabaseCount('users', 0);
    }
}
