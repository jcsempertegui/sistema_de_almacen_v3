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
if (! $app->environment('testing') || $db->selectOne('SELECT DATABASE() AS nombre')->nombre !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Isolation rejected');
}
if ($db->table('migrations')->count() !== 29 || $db->table('usuarios')->count() !== 0 || $db->table('productos')->count() !== 0) {
    throw new RuntimeException('Rehearsal requires the empty S3 testing schema');
}
$fixtures = new class
{
    use CreatesInventory;

    public function context(string $mode): array
    {
        return $this->inventoryContext($mode);
    }

    public function post(array $c, string $clase, string $cantidad): void
    {
        $this->postInventory($c, $clase, $cantidad);
    }
};
$contexts = [];
try {
    $contexts[] = $q = $fixtures->context('cantidad');
    $fixtures->post($q, 'receipt', '5');
    $fixtures->post($q, 'issue', '2');
    $contexts[] = $i = $fixtures->context('individual');
    $fixtures->post($i, 'receipt', '1');
    $fixtures->post($i, 'issue', '1');
    $fixtures->post($i, 'receipt', '1');
    $contexts[] = $o = $fixtures->context('cantidad');
    $fixtures->post($o, 'opening', '2');
    $tables = ['documentos_inventario', 'lineas_documento_inventario', 'unidades_linea_documento', 'movimientos_inventario', 'existencias_ubicacion', 'posiciones_unidad_individual', 'productos_existencia_inicial'];
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
    $resultado = ['database' => 'sga_rio_grande_testing', 'applied_migrations' => $db->table('migrations')->count(), 'historical_rows_preserved' => array_map('count', $before), 'new_tables' => $db->selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE'")->n, 'permisos' => $db->table('permisos')->count(), 'success' => true];
    echo json_encode($resultado, JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    // Only test records created above; preserve schema, permisos and migration audit.
    $productos = array_map(fn ($c) => $c['producto']->id, $contexts);
    $actors = array_map(fn ($c) => $c['actor']->id, $contexts);
    $documents = $db->table('documentos_inventario')->whereIn('creado_por', $actors)->pluck('id');
    $lineas = $db->table('lineas_documento_inventario')->whereIn('documento_id', $documents)->pluck('id');
    $db->table('posiciones_unidad_individual')->whereIn('producto_id', $productos)->delete();
    $db->table('productos_existencia_inicial')->whereIn('producto_id', $productos)->delete();
    $db->table('movimientos_inventario')->whereIn('linea_documento_id', $lineas)->delete();
    $db->table('unidades_linea_documento')->whereIn('linea_documento_id', $lineas)->delete();
    $db->table('lineas_documento_inventario')->whereIn('id', $lineas)->delete();
    $db->table('documentos_inventario')->whereIn('id', $documents)->delete();
    $db->table('existencias_ubicacion')->whereIn('producto_id', $productos)->delete();
    $db->table('unidades_individuales')->whereIn('producto_id', $productos)->delete();
    $db->table('ubicaciones_producto')->whereIn('producto_id', $productos)->delete();
    $db->table('productos')->whereIn('id', $productos)->delete();
    foreach ($contexts as $c) {
        $db->table('ubicaciones')->where('id', $c['ubicacion']->id)->delete();
        $db->table('categorias')->where('id', $c['producto']->categoria_id)->delete();
        $db->table('unidades_medida')->where('id', $c['producto']->unidad_medida_id)->delete();
    }
    $db->table('usuarios')->whereIn('id', $actors)->delete();
}
