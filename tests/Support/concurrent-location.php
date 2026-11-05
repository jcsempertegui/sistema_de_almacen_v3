<?php

use App\Actions\Catalogo\GuardarUbicacion;
use App\Models\Ubicacion;
use App\Models\Usuario;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertConnection($app->make('db')->connection());
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = Usuario::with('rol')->findOrFail($job['actor']);
if ($job['snapshot']) {
    DB::beginTransaction();
    Ubicacion::orderBy('id')->get(); // Force a read view BEFORE either mutation.
}
$targetId = $job['mode'] === 'cycle' && $job['index'] === 1 ? $job['b'] : $job['a'];
if ($job['mode'] === 'activate' && $job['index'] === 1) {
    $targetId = $job['b'];
}
$target = Ubicacion::findOrFail($targetId);
$wait = 0;
DB::listen(function ($query) use (&$wait) {
    if (str_contains($query->sql, '`permisos`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
        $wait = max($wait, $query->time);
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
        app(GuardarUbicacion::class)->handle($actor, $target, ['padre_id' => $job['index'] === 0 ? $job['b'] : $job['a']]);
    } elseif ($job['index'] === 0) {
        app(GuardarUbicacion::class)->handle($actor, $target, ['activo' => false], statusOnly: true);
    } elseif ($job['mode'] === 'create') {
        app(GuardarUbicacion::class)->handle($actor, null, ['codigo' => $job['child_code'], 'nombre' => 'Hijo concurrente', 'tipo' => 'cabinet', 'padre_id' => $job['a'], 'permite_almacenar' => true, 'activo' => true]);
    } else {
        app(GuardarUbicacion::class)->handle($actor, $target, ['activo' => true], statusOnly: true);
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
