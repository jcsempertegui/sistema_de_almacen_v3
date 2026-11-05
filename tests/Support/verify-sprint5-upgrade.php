<?php

use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Devoluciones\GuardarDevolucion;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Prestamos\GuardarPrestamo;
use App\Models\Trabajador;
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
if (! $app->environment('testing') || $db->selectOne('SELECT DATABASE() AS nombre')->nombre !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Isolation rejected');
}
if ($db->table('migrations')->count() !== 35 || $db->table('usuarios')->count() !== 0 || $db->table('productos')->count() !== 0) {
    throw new RuntimeException('Rehearsal requires the empty S4 testing schema');
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
$trabajador = null;
try {
    $contexts[] = $q = $fixtures->context('cantidad');
    $fixtures->post($q, 'receipt', '7');
    $contexts[] = $c = $fixtures->context('individual');
    $c['producto']->update(['prestable' => true]);
    $fixtures->post($c, 'receipt', '1');
    $trabajador = Trabajador::factory()->create();
    $makeLoan = function () use ($c, $trabajador) {
        $d = app(GuardarPrestamo::class)->handle($c['actor'], null, ['trabajador_id' => $trabajador->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['unidad_individual_id' => $c['unidad']->id, 'ubicacion_id' => $c['ubicacion']->id]]]);

        return app(ConfirmarPrestamo::class)->handle($c['actor'], $d, 1);
    };
    $prestamo = $makeLoan();
    $ret = app(GuardarDevolucion::class)->handle($c['actor'], null, ['prestamo_id' => $prestamo->id, 'fecha_documento' => '2026-10-20', 'expected_line_count' => 1, 'lineas' => [['item_prestamo_id' => $prestamo->lineas()->first()->id, 'ubicacion_id' => $c['ubicacion']->id]]]);
    app(ConfirmarDevolucion::class)->handle($c['actor'], $ret, 1);
    $pending = $makeLoan();
    $tables = ['documentos_inventario', 'lineas_documento_inventario', 'unidades_linea_documento', 'movimientos_inventario', 'existencias_ubicacion', 'posiciones_unidad_individual', 'prestamos_herramientas', 'items_prestamo_herramienta', 'devoluciones_herramientas', 'items_devolucion_herramienta', 'custodias_unidad_individual', 'cierres_item_prestamo'];
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
    if ($db->table('ciclos_custodia_prestamo')->count() !== 2 || $db->table('cierres_ciclo_prestamo')->count() !== 1 || $db->table('custodias_unidad_individual')->whereNotNull('ciclo_actual_id')->count() !== 1) {
        throw new RuntimeException('Historical cycle projection mismatch');
    }
    echo json_encode(['database' => 'sga_rio_grande_testing', 'migrations_before' => 35, 'migrations_after' => $db->table('migrations')->count(), 'historical_rows_preserved' => array_map('count', $before), 'cycles' => 2, 'closures' => 1, 'success' => true], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    $productos = array_map(fn ($c) => $c['producto']->id, $contexts);
    $actors = array_map(fn ($c) => $c['actor']->id, $contexts);
    if ($productos) {
        $loans = $db->table('prestamos_herramientas')->whereIn('creado_por', $actors)->pluck('id');
        $items = $db->table('items_prestamo_herramienta')->whereIn('prestamo_id', $loans)->pluck('id');
        $returns = $db->table('devoluciones_herramientas')->whereIn('creado_por', $actors)->pluck('id');
        if (Schema::hasTable('ciclos_custodia_prestamo')) {
            $cycles = $db->table('ciclos_custodia_prestamo')->whereIn('producto_id', $productos)->pluck('id');
            $db->table('custodias_unidad_individual')->whereIn('producto_id', $productos)->delete();
            $db->table('items_devolucion_herramienta')->whereIn('devolucion_id', $returns)->update(['ciclo_custodia_id' => null]);
            $db->table('cierres_ciclo_prestamo')->whereIn('ciclo_id', $cycles)->delete();
            $db->table('ciclos_custodia_prestamo')->whereIn('id', $cycles)->delete();
        } else {
            $db->table('custodias_unidad_individual')->whereIn('producto_id', $productos)->delete();
        }
        $db->table('posiciones_unidad_individual')->whereIn('producto_id', $productos)->delete();
        $db->table('cierres_item_prestamo')->whereIn('item_prestamo_id', $items)->delete();
        $db->table('movimientos_inventario')->whereIn('producto_id', $productos)->delete();
        $db->table('items_devolucion_herramienta')->whereIn('devolucion_id', $returns)->delete();
        $db->table('devoluciones_herramientas')->whereIn('id', $returns)->delete();
        $db->table('items_prestamo_herramienta')->whereIn('id', $items)->delete();
        $db->table('prestamos_herramientas')->whereIn('id', $loans)->delete();
        $docs = $db->table('documentos_inventario')->whereIn('creado_por', $actors)->pluck('id');
        $lineas = $db->table('lineas_documento_inventario')->whereIn('documento_id', $docs)->pluck('id');
        $db->table('unidades_linea_documento')->whereIn('linea_documento_id', $lineas)->delete();
        $db->table('lineas_documento_inventario')->whereIn('id', $lineas)->delete();
        $db->table('documentos_inventario')->whereIn('id', $docs)->delete();
        $db->table('existencias_ubicacion')->whereIn('producto_id', $productos)->delete();
        $db->table('unidades_individuales')->whereIn('producto_id', $productos)->delete();
        $db->table('ubicaciones_producto')->whereIn('producto_id', $productos)->delete();
        $db->table('productos')->whereIn('id', $productos)->delete();
        foreach ($contexts as $ctx) {
            $db->table('ubicaciones')->where('id', $ctx['ubicacion']->id)->delete();
            $db->table('categorias')->where('id', $ctx['producto']->categoria_id)->delete();
            $db->table('unidades_medida')->where('id', $ctx['producto']->unidad_medida_id)->delete();
        }$db->table('usuarios')->whereIn('id', $actors)->delete();
    }if ($trabajador) {
        $db->table('trabajadores')->where('id', $trabajador->id)->delete();
    }
}
