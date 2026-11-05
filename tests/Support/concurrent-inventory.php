<?php

use App\Actions\Catalogo\GuardarProducto;
use App\Actions\Catalogo\GuardarUbicacionProducto;
use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Actions\Inventario\CancelarDocumentoInventario;
use App\Actions\Inventario\ConfirmarDocumentoInventario;
use App\Actions\Roles\GuardarRol;
use App\Models\DocumentoInventario;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\Rol;
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
DB::table('existencias_ubicacion')->get(); // Deliberately pin an old REPEATABLE READ view.
$documento = DocumentoInventario::findOrFail($job['documento']);
$wait = 0;
DB::listen(function ($query) use ($job, &$wait) {
    if (str_contains($query->sql, '`permisos`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
        $wait = max($wait, $query->time);
        echo "LOCKED\n";
        flush();
        if ($job['index'] === 0 && trim((string) fgets(STDIN)) !== 'RELEASE') {
            throw new RuntimeException('No se recibió la barrera.');
        }
    }
});
echo 'CONNECTION_ID:'.DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\nREADY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(2);
}
try {
    match ($job['operation']) {
        'confirm' => app(ConfirmarDocumentoInventario::class)->handle($actor, $documento, 1),
        'cancel' => app(CancelarDocumentoInventario::class)->handle($actor, $documento, ['version_bloqueo' => 1, 'motivo' => 'Cancelación concurrente de prueba']),
        'disable_product' => app(GuardarProducto::class)->handle($actor, Producto::findOrFail($job['producto']), ['activo' => false, 'version_bloqueo' => 1], true),
        'disable_pair' => app(GuardarUbicacionProducto::class)->handle($actor, Producto::findOrFail($job['producto']), UbicacionProducto::findOrFail($job['pair']), ['activo' => false, 'version_bloqueo' => 1]),
        'disable_unit' => app(GuardarUnidadIndividual::class)->handle($actor, UnidadIndividual::findOrFail($job['unidad']), ['activo' => false, 'version_bloqueo' => 1], true),
        'revoke' => app(GuardarRol::class)->handle($actor, Rol::findOrFail($job['other_role']), ['permisos' => Permiso::where('codigo', '<>', 'remittances.confirm_receipt')->pluck('id')->all()]),
        default => throw new RuntimeException('Operación desconocida.'),
    };
    DB::commit();
    echo "RESULT:changed\n";
} catch (AuthorizationException|ValidationException|HttpException $e) {
    DB::rollBack();
    echo 'RESULT:protected '.get_class($e)."\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
