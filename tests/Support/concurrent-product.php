<?php

use App\Actions\Catalog\SaveCategoryAttribute;
use App\Actions\Catalog\SaveLocation;
use App\Actions\Catalog\SaveProduct;
use App\Actions\Catalog\SaveProductLocation;
use App\Actions\Roles\SaveRole;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Product;
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
Product::orderBy('id')->get(); // Deliberately retain a read view from BEFORE the winner commits.
$target = $job['product'] ? Product::findOrFail($job['product']) : null;
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
            app(SaveProduct::class)->handle($actor, null, ['internal_code' => $job['code'], 'name' => 'Producto concurrente', 'category_id' => $job['category'], 'measurement_unit_id' => $job['unit'], 'control_mode' => 'quantity', 'is_loanable' => false, 'is_active' => true, 'values' => []]);
            break;
        case 'edit':
            app(SaveProduct::class)->handle($actor, $target, ['name' => 'Editado por '.$job['index'], 'lock_version' => 1]);
            break;
        case 'disable':
            app(SaveLocation::class)->handle($actor, Location::findOrFail($job['location']), ['is_active' => false], statusOnly: true);
            break;
        case 'link':
            app(SaveProductLocation::class)->handle($actor, $target, null, ['location_id' => $job['location'], 'is_active' => true, 'lock_version' => 1]);
            break;
        case 'required':
            app(SaveCategoryAttribute::class)->handle($actor, Category::findOrFail($job['category']), CategoryAttribute::findOrFail($job['association']), ['is_required' => true]);
            break;
        case 'revoke':
            $role = Role::findOrFail($job['other_role']);
            app(SaveRole::class)->handle($actor, $role, ['permissions' => Permission::where('code', '<>', 'products.create')->pluck('id')->all()]);
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
