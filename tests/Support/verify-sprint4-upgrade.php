<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
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
if ($db->table('migrations')->count() !== 29 || $db->table('users')->count() !== 0 || $db->table('products')->count() !== 0) {
    throw new RuntimeException('Rehearsal requires the empty S3 testing schema');
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
try {
    $contexts[] = $q = $fixtures->context('quantity');
    $fixtures->post($q, 'receipt', '5');
    $fixtures->post($q, 'issue', '2');
    $contexts[] = $i = $fixtures->context('individual');
    $fixtures->post($i, 'receipt', '1');
    $fixtures->post($i, 'issue', '1');
    $fixtures->post($i, 'receipt', '1');
    $contexts[] = $o = $fixtures->context('quantity');
    $fixtures->post($o, 'opening', '2');
    $tables = ['inventory_documents', 'inventory_document_lines', 'inventory_document_line_units', 'inventory_movements', 'stock_positions', 'individual_unit_positions', 'initial_stock_products'];
    $before = [];
    foreach ($tables as $t) {
        $before[$t] = $db->table($t)->get()->map(fn ($r) => (array) $r)->all();
    }
    $kernel = $app->make(Kernel::class);
    $paths = array_map(fn ($p) => 'database/migrations/'.basename($p), glob(dirname(__DIR__, 2).'/database/migrations/2026_10_19_00000[3-8]*.php'));
    if (count($paths) !== 6 || $kernel->call('migrate', ['--force' => true, '--path' => $paths]) !== 0) {
        throw new RuntimeException('Upgrade failed: '.$kernel->output());
    }
    foreach ($tables as $t) {
        $after = $db->table($t)->get()->map(fn ($r) => (array) $r)->all();
        if (count($before[$t]) !== count($after)) {
            throw new RuntimeException('Row count changed: '.$t);
        }
        foreach ($before[$t] as $index => $row) {
            if ($row !== array_intersect_key($after[$index], $row)) {
                throw new RuntimeException('Historical value changed: '.$t);
            }
        }
    }
    $result = ['database' => 'sga_rio_grande_testing', 'applied_migrations' => $db->table('migrations')->count(), 'historical_rows_preserved' => array_map('count', $before), 'new_tables' => $db->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->n, 'permissions' => $db->table('permissions')->count(), 'success' => true];
    echo json_encode($result, JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    // Only test records created above; preserve schema, permissions and migration audit.
    $products = array_map(fn ($c) => $c['product']->id, $contexts);
    $actors = array_map(fn ($c) => $c['actor']->id, $contexts);
    $documents = $db->table('inventory_documents')->whereIn('created_by', $actors)->pluck('id');
    $lines = $db->table('inventory_document_lines')->whereIn('document_id', $documents)->pluck('id');
    $db->table('individual_unit_positions')->whereIn('product_id', $products)->delete();
    $db->table('initial_stock_products')->whereIn('product_id', $products)->delete();
    $db->table('inventory_movements')->whereIn('document_line_id', $lines)->delete();
    $db->table('inventory_document_line_units')->whereIn('document_line_id', $lines)->delete();
    $db->table('inventory_document_lines')->whereIn('id', $lines)->delete();
    $db->table('inventory_documents')->whereIn('id', $documents)->delete();
    $db->table('stock_positions')->whereIn('product_id', $products)->delete();
    $db->table('individual_units')->whereIn('product_id', $products)->delete();
    $db->table('product_locations')->whereIn('product_id', $products)->delete();
    $db->table('products')->whereIn('id', $products)->delete();
    foreach ($contexts as $c) {
        $db->table('locations')->where('id', $c['location']->id)->delete();
        $db->table('categories')->where('id', $c['product']->category_id)->delete();
        $db->table('measurement_units')->where('id', $c['product']->measurement_unit_id)->delete();
    }
    $db->table('users')->whereIn('id', $actors)->delete();
}
