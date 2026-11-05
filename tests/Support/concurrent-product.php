<?php

use App\Actions\Catalogo\GuardarAtributoCategoria;
use App\Actions\Catalogo\GuardarProducto;
use App\Actions\Catalogo\GuardarUbicacion;
use App\Actions\Catalogo\GuardarUbicacionProducto;
use App\Actions\Roles\GuardarRol;
use App\Models\AtributoCategoria;
use App\Models\Categoria;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Ubicacion;
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
Producto::orderBy('id')->get(); // Deliberately retain a read view from BEFORE the winner commits.
$target = $job['producto'] ? Producto::findOrFail($job['producto']) : null;
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
echo "GO_RECEIVED\n";
flush();
try {
    switch ($job['operation']) {
        case 'create':
            app(GuardarProducto::class)->handle($actor, null, ['codigo_interno' => $job['codigo'], 'nombre' => 'Producto concurrente', 'categoria_id' => $job['categoria'], 'unidad_medida_id' => $job['unidad'], 'modo_control' => 'quantity', 'prestable' => false, 'activo' => true, 'values' => []]);
            break;
        case 'edit':
            app(GuardarProducto::class)->handle($actor, $target, ['nombre' => 'Editado por '.$job['index'], 'version_bloqueo' => 1]);
            break;
        case 'disable':
            app(GuardarUbicacion::class)->handle($actor, Ubicacion::findOrFail($job['ubicacion']), ['activo' => false], statusOnly: true);
            break;
        case 'link':
            app(GuardarUbicacionProducto::class)->handle($actor, $target, null, ['ubicacion_id' => $job['ubicacion'], 'activo' => true, 'version_bloqueo' => 1]);
            break;
        case 'required':
            app(GuardarAtributoCategoria::class)->handle($actor, Categoria::findOrFail($job['categoria']), AtributoCategoria::findOrFail($job['association']), ['obligatorio' => true]);
            break;
        case 'revoke':
            $rol = Rol::findOrFail($job['other_role']);
            app(GuardarRol::class)->handle($actor, $rol, ['permisos' => Permiso::where('codigo', '<>', 'products.create')->pluck('id')->all()]);
            break;
        default: throw new RuntimeException('Operación de prueba desconocida.');
    }
    DB::commit();
    echo "RESULT:changed\n";
} catch (ValidationException|AuthorizationException $e) {
    DB::rollBack();
    echo "RESULT:protected\n";
} catch (HttpException $e) {
    DB::rollBack();
    if ($e->getStatusCode() !== 409) {
        throw $e;
    }
    echo "RESULT:protected\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
