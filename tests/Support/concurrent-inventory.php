<?php

use App\Actions\Catalog\SaveIndividualUnit;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductLocation;
use App\Actions\Inventory\CancelInventoryDocument;
use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Roles\SaveRole;
use App\Models\IndividualUnit;
use App\Models\InventoryDocument;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'sga_rio_grande_testing' || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_URL') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Destino concurrente no autorizado.');
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::selectOne('SELECT DATABASE() AS db')->db !== 'sga_rio_grande_testing') {
    throw new RuntimeException('No existe aislamiento.');
}
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = User::with('role')->findOrFail($job['actor']);
DB::beginTransaction();
DB::table('stock_positions')->get(); // Deliberately pin an old REPEATABLE READ view.
$document = InventoryDocument::findOrFail($job['document']);
$wait = 0;
DB::listen(function ($query) use ($job, &$wait) {
    if (str_contains($query->sql, '`permissions`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
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
        'confirm' => app(ConfirmInventoryDocument::class)->handle($actor, $document, 1),
        'cancel' => app(CancelInventoryDocument::class)->handle($actor, $document, ['lock_version' => 1, 'reason' => 'Cancelación concurrente de prueba']),
        'disable_product' => app(SaveProduct::class)->handle($actor, Product::findOrFail($job['product']), ['is_active' => false, 'lock_version' => 1], true),
        'disable_pair' => app(SaveProductLocation::class)->handle($actor, Product::findOrFail($job['product']), ProductLocation::findOrFail($job['pair']), ['is_active' => false, 'lock_version' => 1]),
        'disable_unit' => app(SaveIndividualUnit::class)->handle($actor, IndividualUnit::findOrFail($job['unit']), ['is_active' => false, 'lock_version' => 1], true),
        'revoke' => app(SaveRole::class)->handle($actor, Role::findOrFail($job['other_role']), ['permissions' => Permission::where('code', '<>', 'remittances.confirm_receipt')->pluck('id')->all()]),
        default => throw new RuntimeException('Operación desconocida.'),
    };
    DB::commit();
    echo "RESULT:changed\n";
} catch (AuthorizationException|ValidationException|HttpException $e) {
    DB::rollBack();
    echo 'RESULT:protected '.get_class($e)."\n";
}
echo 'LOCK_WAIT:'.$wait."\n";
