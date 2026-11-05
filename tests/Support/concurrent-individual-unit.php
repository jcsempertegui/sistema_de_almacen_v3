<?php

use App\Actions\Catalogo\GuardarProducto;
use App\Actions\Catalogo\GuardarUbicacion;
use App\Actions\Catalogo\GuardarUbicacionProducto;
use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
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
UnidadIndividual::orderBy('id')->get(); // Retain an old REPEATABLE READ snapshot before either writer commits.
$producto = Producto::findOrFail($job['producto']);
$unidad = $job['individual'] ? UnidadIndividual::findOrFail($job['individual']) : null;
$wait = 0;
DB::listen(function ($query) use (&$wait, $job) {
    if (str_contains($query->sql, '`permisos`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
        $wait = max($wait, $query->time);
        echo "LOCKED\n";
        flush();
        if ($job['index'] === 0 && trim((string) fgets(STDIN)) !== 'RELEASE') {
            throw new RuntimeException('Missing release barrier.');
        }
    }
});
echo 'CONNECTION_ID:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
try {
    switch ($job['operation']) {
        case 'create':
            app(GuardarUnidadIndividual::class)->handle($actor, null, ['identificador' => $job['identificador'], 'producto_id' => $job['producto'], 'ubicacion_id' => $job['ubicacion'], 'numero_serie' => $job['serial'], 'observaciones' => null, 'activo' => true]);
            break;
        case 'edit':
            $data = array_intersect_key($unidad->getAttributes(), array_flip($unidad->getFillable()));
            app(GuardarUnidadIndividual::class)->handle($actor, $unidad, array_replace($data, ['observaciones' => 'Editado por '.$job['index'], 'version_bloqueo' => 1]));
            break;
        case 'disable_product':
            app(GuardarProducto::class)->handle($actor, $producto, ['activo' => false, 'version_bloqueo' => 1], statusOnly: true);
            break;
        case 'disable_location':
            app(GuardarUbicacion::class)->handle($actor, Ubicacion::findOrFail($job['ubicacion']), ['activo' => false], statusOnly: true);
            break;
        case 'disable_link':
            app(GuardarUbicacionProducto::class)->handle($actor, $producto, UbicacionProducto::findOrFail($job['link']), ['activo' => false, 'version_bloqueo' => 1]);
            break;
        case 'revoke':
            app(GuardarRol::class)->handle($actor, Rol::findOrFail($job['other_role']), ['permisos' => Permiso::where('codigo', '<>', 'individual_units.create')->pluck('id')->all()]);
            break;
        default: throw new RuntimeException('Operación de prueba desconocida.');
    }
    DB::commit();
    echo "RESULT:changed\n";
} catch (ValidationException|AuthorizationException $e) {
    DB::rollBack();
    echo 'PROTECTED:'.json_encode($e instanceof ValidationException ? $e->errors() : $e->getMessage(), JSON_THROW_ON_ERROR)."\n";
    echo "RESULT:protected\n";
} catch (HttpException $e) {
    DB::rollBack();
    if ($e->getStatusCode() !== 409) {
        throw $e;
    }
    echo 'PROTECTED:'.$e->getMessage()."\n";
    echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
