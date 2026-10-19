<?php

use App\Actions\Catalog\SaveIndividualUnit;
use App\Actions\Catalog\SaveLocation;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductLocation;
use App\Actions\Roles\SaveRole;
use App\Models\IndividualUnit;
use App\Models\Location;
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
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql' || getenv('DB_DATABASE') !== 'sga_rio_grande_testing' || getenv('DB_URL') || is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php')) {
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
DB::beginTransaction();
IndividualUnit::orderBy('id')->get(); // Retain an old REPEATABLE READ snapshot before either writer commits.
$product = Product::findOrFail($job['product']);
$unit = $job['individual'] ? IndividualUnit::findOrFail($job['individual']) : null;
$wait = 0;
DB::listen(function ($query) use (&$wait, $job) {
    if (str_contains($query->sql, '`permissions`') && str_contains($query->sql, 'for update') && str_contains($query->sql, 'limit 1')) {
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
            app(SaveIndividualUnit::class)->handle($actor, null, ['identifier' => $job['identifier'], 'product_id' => $job['product'], 'location_id' => $job['location'], 'serial_number' => $job['serial'], 'notes' => null, 'is_active' => true]);
            break;
        case 'edit':
            $data = array_intersect_key($unit->getAttributes(), array_flip($unit->getFillable()));
            app(SaveIndividualUnit::class)->handle($actor, $unit, array_replace($data, ['notes' => 'Editado por '.$job['index'], 'lock_version' => 1]));
            break;
        case 'disable_product':
            app(SaveProduct::class)->handle($actor, $product, ['is_active' => false, 'lock_version' => 1], statusOnly: true);
            break;
        case 'disable_location':
            app(SaveLocation::class)->handle($actor, Location::findOrFail($job['location']), ['is_active' => false], statusOnly: true);
            break;
        case 'disable_link':
            app(SaveProductLocation::class)->handle($actor, $product, ProductLocation::findOrFail($job['link']), ['is_active' => false, 'lock_version' => 1]);
            break;
        case 'revoke':
            app(SaveRole::class)->handle($actor, Role::findOrFail($job['other_role']), ['permissions' => Permission::where('code', '<>', 'individual_units.create')->pluck('id')->all()]);
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
