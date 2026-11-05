<?php

use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Actions\Devoluciones\ConfirmarDevolucion;
use App\Actions\Entregas\ConfirmarEntrega;
use App\Actions\Prestamos\ConfirmarPrestamo;
use App\Actions\Roles\GuardarRol;
use App\Actions\Trabajadores\GuardarTrabajador;
use App\Models\AutorizacionRegularizacionDevolucion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Trabajador;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use App\Services\RegularizacionDevolucion;
use App\Support\TestingDatabaseGuard;
use App\Support\TiposOperacionTrabajador;
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
$class = TiposOperacionTrabajador::MODELS[$job['tipo']];
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
        app(GuardarRol::class)->handle($actor, Rol::findOrFail($job['rol']), ['permisos' => Permiso::where('codigo', '<>', $job['tipo'].'.confirm')->pluck('id')->all()]);
    } elseif ($job['operation'] === 'disable_worker') {
        app(GuardarTrabajador::class)->handle($actor, Trabajador::findOrFail($doc->trabajador_id), ['activo' => false], true);
    } elseif ($job['operation'] === 'disable_unit') {
        app(GuardarUnidadIndividual::class)->handle($actor, UnidadIndividual::findOrFail($doc->lineas()->first()->unidad_individual_id), ['activo' => false, 'version_bloqueo' => 1], true);
    } elseif ($job['operation'] === 'revoke_regularization') {
        app(RegularizacionDevolucion::class)->decide($actor, AutorizacionRegularizacionDevolucion::findOrFail($job['authorization']), false, 'Revocación concurrente');
    } else {
        $accion = ['deliveries' => ConfirmarEntrega::class, 'loans' => ConfirmarPrestamo::class, 'returns' => ConfirmarDevolucion::class][$job['tipo']];
        app($accion)->handle($actor, $doc, 1);
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
