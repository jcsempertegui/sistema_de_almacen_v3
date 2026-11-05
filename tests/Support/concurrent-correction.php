<?php

use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Roles\GuardarRol;
use App\Models\CorreccionInventario;
use App\Models\Permiso;
use App\Models\PrestamoHerramienta;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\CorreccionesInventario;
use App\Support\TestingDatabaseGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertConnection($app->make('db')->connection());
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = Usuario::with('rol')->findOrFail($job['actor']);
DB::beginTransaction();
DB::table('existencias_ubicacion')->get();
$class = CorreccionInventario::class;
$doc = $class::findOrFail($job['documento']);
$wait = 0;
DB::listen(function ($query) use ($job, &$wait) {
    if (str_contains($query->sql, '`permisos`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
        $wait = max($wait, $query->time);
        echo "LOCKED\n";
        flush();
        if ($job['index'] === 0 && trim((string) fgets(STDIN)) !== 'RELEASE') {
            throw new RuntimeException('Missing release');
        }
    }
});
echo 'CONNECTION_ID:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\nREADY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
echo "GO_RECEIVED\n";
flush();

try {
    if ($job['operation'] === 'revoke') {
        app(CorreccionesInventario::class)->transition($actor, $doc, 'revoke', 1, 'Revocación concurrente');
    } elseif ($job['operation'] === 'revoke_role') {
        app(GuardarRol::class)->handle($actor, Rol::findOrFail($job['rol']), ['permisos' => Permiso::where('codigo', '<>', 'adjustments.execute')->pluck('id')->all()]);
    } elseif ($job['operation'] === 'disable_authorizer_role') {
        app(GuardarRol::class)->handle($actor, Rol::findOrFail($job['rol']), ['activo' => false]);
    } elseif ($job['operation'] === 'reloan') {
        app(ConfirmarPrestamo::class)->handle($actor, PrestamoHerramienta::findOrFail($job['authorization']), 1);
    } else {
        app(CorreccionesInventario::class)->execute($actor, $doc, 1);
    }
    DB::commit();
    echo "RESULT:changed\n";
} catch (AuthorizationException|ValidationException $e) {
    DB::rollBack();
    echo "RESULT:protected\n";
} catch (HttpException $e) {
    DB::rollBack();
    if ($e->getStatusCode() !== 409) {
        throw $e;
    }echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
