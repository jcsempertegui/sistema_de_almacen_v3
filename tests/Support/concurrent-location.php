<?php

use App\Actions\Catalog\SaveLocation;
use App\Models\Location;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql'
    || getenv('DB_DATABASE') !== 'sga_rio_grande_testing' || getenv('DB_URL')
    || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Destino de concurrencia no autorizado.');
}
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname=sga_rio_grande_testing', getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'sga_rio_grande_testing') {
    throw new RuntimeException('La conexión no está aislada.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::selectOne('SELECT DATABASE() AS name')->name !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Laravel no conserva el aislamiento.');
}
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = User::with('role')->findOrFail($job['actor']);
if ($job['snapshot']) {
    DB::beginTransaction();
    Location::orderBy('id')->get(); // Force a read view BEFORE either mutation.
}
$targetId = $job['mode'] === 'cycle' && $job['index'] === 1 ? $job['b'] : $job['a'];
if ($job['mode'] === 'activate' && $job['index'] === 1) {
    $targetId = $job['b'];
}
$target = Location::findOrFail($targetId);
$wait = 0;
DB::listen(function ($query) use (&$wait) {
    if (str_contains($query->sql, '`permissions`') && str_contains($query->sql, 'for update')) {
        $wait = $query->time;
        usleep(300000);
    }
});
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
try {
    if ($job['mode'] === 'cycle') {
        app(SaveLocation::class)->handle($actor, $target, ['parent_id' => $job['index'] === 0 ? $job['b'] : $job['a']]);
    } elseif ($job['index'] === 0) {
        app(SaveLocation::class)->handle($actor, $target, ['is_active' => false], statusOnly: true);
    } elseif ($job['mode'] === 'create') {
        app(SaveLocation::class)->handle($actor, null, ['code' => $job['child_code'], 'name' => 'Hijo concurrente', 'type' => 'cabinet', 'parent_id' => $job['a'], 'is_storable' => true, 'is_active' => true]);
    } else {
        app(SaveLocation::class)->handle($actor, $target, ['is_active' => true], statusOnly: true);
    }
    if ($job['snapshot']) {
        DB::commit();
    }
    echo "RESULT:changed\n";
} catch (ValidationException) {
    if ($job['snapshot']) {
        DB::rollBack();
    }
    echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
