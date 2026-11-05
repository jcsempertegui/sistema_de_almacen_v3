<?php

use App\Models\Usuario;
use App\Services\RespaldosBaseDatos;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;

// Standalone upgrade rehearsal. Never resets schemas or touches the application DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';
if (! in_array('--authorized-real-backup', $argv, true)) {
    throw new RuntimeException('Este ensayo genera un respaldo real y requiere autorización específica antes de ejecutarlo.');
}
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
if ($db->table('migrations')->count() !== 43 || $db->table('usuarios')->count() !== 0 || $db->table('productos')->count() !== 0) {
    throw new RuntimeException('Rehearsal requires the empty S5 testing schema');
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

$actor = null;
$run = null;
try {
    $actor = Usuario::factory()->create(['rol_id' => 1]);
    $service = app(RespaldosBaseDatos::class);
    $run = $service->request($actor);
    $run = $service->generate($actor, $run);
    $run = $service->verify($actor, $run);
    $path = $service->download($actor, $run);
    echo json_encode(['database' => $db->selectOne('SELECT DATABASE() AS nombre')->nombre, 'real_mariadb_dump_executed' => true, 'tamano_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path), 'tables' => count($run->manifiesto['tables']), 'integrity' => $run->manifiesto['integrity'], 'source_manifest_matches' => true, 'credentials_removed' => glob(dirname($path).'/*.cnf') === [], 'real_restore_executed' => false, 'main_touched' => false, 'fixtures_cleaned_afterward' => true], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    if ($run) {
        $db->table('eventos_operacion_critica')->where('ejecucion_respaldo_id', $run->id)->delete();
        $db->table('ejecuciones_respaldo')->where('id', $run->id)->delete();
    }
    if ($actor) {
        $db->table('usuarios')->where('id', $actor->id)->delete();
    }
}
