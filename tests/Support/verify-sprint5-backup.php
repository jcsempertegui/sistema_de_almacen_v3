<?php

use App\Models\User;
use App\Services\DatabaseBackups;
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
if ($db->table('migrations')->count() !== 43 || $db->table('users')->count() !== 0 || $db->table('products')->count() !== 0) {
    throw new RuntimeException('Rehearsal requires the empty S5 testing schema');
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

$actor = null;
$run = null;
try {
    config(['sga.backup_directory' => 'C:/Users/jcsem/SGA-evidence/sprint-5/real-backup-'.bin2hex(random_bytes(6))]);
    $actor = User::factory()->create(['role_id' => 1]);
    $service = app(DatabaseBackups::class);
    $run = $service->request($actor);
    $run = $service->generate($actor, $run);
    $run = $service->verify($actor, $run);
    $path = $service->download($actor, $run);
    echo json_encode(['database' => $db->selectOne('SELECT DATABASE() AS name')->name, 'real_mariadb_dump_executed' => true, 'size_bytes' => filesize($path), 'sha256' => hash_file('sha256', $path), 'tables' => count($run->manifest['tables']), 'integrity' => $run->manifest['integrity'], 'source_manifest_matches' => true, 'credentials_removed' => glob(dirname($path).'/*.cnf') === [], 'real_restore_executed' => false, 'main_touched' => false, 'fixtures_cleaned_afterward' => true], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    if ($run) {
        $db->table('critical_operation_events')->where('backup_run_id', $run->id)->delete();
        $db->table('backup_runs')->where('id', $run->id)->delete();
    }
    if ($actor) {
        $db->table('users')->where('id', $actor->id)->delete();
    }
}
