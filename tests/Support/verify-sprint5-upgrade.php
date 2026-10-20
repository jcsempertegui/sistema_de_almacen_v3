<?php

use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Models\Worker;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesInventory;

// Standalone upgrade rehearsal. Never resets schemas or touches the application DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'BCRYPT_ROUNDS' => '4', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'] as $k => $v) {
    $_ENV[$k] = $_SERVER[$k] = $v;
    putenv($k.'='.$v);
}
require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->selectOne('SELECT DATABASE() AS name')->name !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Isolation rejected');
}
if ($db->table('migrations')->count() !== 35 || $db->table('users')->count() !== 0 || $db->table('products')->count() !== 0) {
    throw new RuntimeException('Rehearsal requires the empty S4 testing schema');
}
$fixtures = new class
{
    use CreatesInventory;

    public function context(string $mode): array
    {
        return $this->inventoryContext($mode);
    }

    public function post(array $c, string $kind, string $quantity): void
    {
        $this->postInventory($c, $kind, $quantity);
    }
};

$contexts = [];
$worker = null;
try {
    $contexts[] = $q = $fixtures->context('quantity');
    $fixtures->post($q, 'receipt', '7');
    $contexts[] = $c = $fixtures->context('individual');
    $c['product']->update(['is_loanable' => true]);
    $fixtures->post($c, 'receipt', '1');
    $worker = Worker::factory()->create();
    $makeLoan = function () use ($c, $worker) {
        $d = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);

        return app(ConfirmLoan::class)->handle($c['actor'], $d, 1);
    };
    $loan = $makeLoan();
    $ret = app(SaveReturn::class)->handle($c['actor'], null, ['loan_id' => $loan->id, 'document_date' => '2026-10-20', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]]);
    app(ConfirmReturn::class)->handle($c['actor'], $ret, 1);
    $pending = $makeLoan();
    $tables = ['inventory_documents', 'inventory_document_lines', 'inventory_document_line_units', 'inventory_movements', 'stock_positions', 'individual_unit_positions', 'tool_loans', 'tool_loan_items', 'tool_returns', 'tool_return_items', 'individual_unit_custodies', 'loan_item_settlements'];
    $before = [];
    foreach ($tables as $t) {
        $before[$t] = $db->table($t)->orderByRaw('1')->get()->map(fn ($r) => (array) $r)->all();
    }
    $paths = array_map(fn ($p) => 'database/migrations/'.basename($p), glob(dirname(__DIR__, 2).'/database/migrations/2026_10_20_*.php'));
    if (count($paths) !== 8) {
        throw new RuntimeException('Unexpected S5 migration set');
    }
    if ($app->make(Kernel::class)->call('migrate', ['--path' => $paths, '--force' => true]) !== 0) {
        throw new RuntimeException($app->make(Kernel::class)->output());
    }
    foreach ($before as $t => $rows) {
        $after = $db->table($t)->orderByRaw('1')->get()->map(fn ($r) => array_intersect_key((array) $r, array_flip(array_keys($rows[0] ?? []))))->all();
        if ($after !== $rows) {
            throw new RuntimeException('Historical columns changed: '.$t);
        }
    }
    if ($db->table('loan_custody_cycles')->count() !== 2 || $db->table('loan_cycle_closures')->count() !== 1 || $db->table('individual_unit_custodies')->whereNotNull('current_cycle_id')->count() !== 1) {
        throw new RuntimeException('Historical cycle projection mismatch');
    }
    echo json_encode(['database' => 'sga_rio_grande_testing', 'migrations_before' => 35, 'migrations_after' => $db->table('migrations')->count(), 'historical_rows_preserved' => array_map('count', $before), 'cycles' => 2, 'closures' => 1, 'success' => true], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    $products = array_map(fn ($c) => $c['product']->id, $contexts);
    $actors = array_map(fn ($c) => $c['actor']->id, $contexts);
    if ($products) {
        $loans = $db->table('tool_loans')->whereIn('created_by', $actors)->pluck('id');
        $items = $db->table('tool_loan_items')->whereIn('loan_id', $loans)->pluck('id');
        $returns = $db->table('tool_returns')->whereIn('created_by', $actors)->pluck('id');
        if (Schema::hasTable('loan_custody_cycles')) {
            $cycles = $db->table('loan_custody_cycles')->whereIn('product_id', $products)->pluck('id');
            $db->table('individual_unit_custodies')->whereIn('product_id', $products)->delete();
            $db->table('tool_return_items')->whereIn('return_id', $returns)->update(['custody_cycle_id' => null]);
            $db->table('loan_cycle_closures')->whereIn('cycle_id', $cycles)->delete();
            $db->table('loan_custody_cycles')->whereIn('id', $cycles)->delete();
        } else {
            $db->table('individual_unit_custodies')->whereIn('product_id', $products)->delete();
        }
        $db->table('individual_unit_positions')->whereIn('product_id', $products)->delete();
        $db->table('loan_item_settlements')->whereIn('loan_item_id', $items)->delete();
        $db->table('inventory_movements')->whereIn('product_id', $products)->delete();
        $db->table('tool_return_items')->whereIn('return_id', $returns)->delete();
        $db->table('tool_returns')->whereIn('id', $returns)->delete();
        $db->table('tool_loan_items')->whereIn('id', $items)->delete();
        $db->table('tool_loans')->whereIn('id', $loans)->delete();
        $docs = $db->table('inventory_documents')->whereIn('created_by', $actors)->pluck('id');
        $lines = $db->table('inventory_document_lines')->whereIn('document_id', $docs)->pluck('id');
        $db->table('inventory_document_line_units')->whereIn('document_line_id', $lines)->delete();
        $db->table('inventory_document_lines')->whereIn('id', $lines)->delete();
        $db->table('inventory_documents')->whereIn('id', $docs)->delete();
        $db->table('stock_positions')->whereIn('product_id', $products)->delete();
        $db->table('individual_units')->whereIn('product_id', $products)->delete();
        $db->table('product_locations')->whereIn('product_id', $products)->delete();
        $db->table('products')->whereIn('id', $products)->delete();
        foreach ($contexts as $ctx) {
            $db->table('locations')->where('id', $ctx['location']->id)->delete();
            $db->table('categories')->where('id', $ctx['product']->category_id)->delete();
            $db->table('measurement_units')->where('id', $ctx['product']->measurement_unit_id)->delete();
        }$db->table('users')->whereIn('id', $actors)->delete();
    }if ($worker) {
        $db->table('workers')->where('id', $worker->id)->delete();
    }
}
