<?php

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardCharts;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

// Static visual-review files. All fixtures are confined to a testing transaction.
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}
require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = $app->make('db')->connection();
TestingDatabaseGuard::assertConnection($db);
$directory = storage_path('app/private/visual-review');
foreach (['', '/css', '/js', '/images'] as $suffix) {
    if (! is_dir($directory.$suffix)) {
        mkdir($directory.$suffix, 0700, true);
    }
}
foreach (['css/sga.css', 'js/sga-ui.js', 'images/confipetrol.png'] as $asset) {
    copy(public_path($asset), $directory.'/'.$asset);
}
$db->beginTransaction();
try {
    $user = User::factory()->create(['role_id' => 1, 'name' => 'Operador de demostración', 'email' => 'visual@example.test']);
    auth()->setUser($user);
    $request = Request::create('http://127.0.0.1:8010/panel.html');
    $dashboardRoute = app('router')->getRoutes()->getByName('dashboard')->bind($request);
    $request->setRouteResolver(fn () => $dashboardRoute);
    $app->instance('request', $request);
    app('url')->setRequest($request);
    $errors = new ViewErrorBag;
    $charts = app(DashboardCharts::class)->forUser($user);
    file_put_contents($directory.'/panel.html', view('dashboard', compact('errors', 'charts'))->render());
    $category = Category::factory()->create(['name' => 'Herramientas y materiales']);
    $unit = MeasurementUnit::factory()->create(['name' => 'Unidad', 'symbol' => 'und']);
    foreach (['Llave combinada 24 mm', 'Guantes de seguridad', 'Válvula de compuerta'] as $index => $name) {
        Product::factory()->create(['name' => $name, 'internal_code' => 'VISUAL-'.($index + 1), 'category_id' => $category->id, 'measurement_unit_id' => $unit->id]);
    }
    $productsRoute = app('router')->getRoutes()->getByName('products.index')->bind($request);
    $request->setRouteResolver(fn () => $productsRoute);
    $products = Product::with(['category', 'measurementUnit'])->paginate(20);
    $categories = Category::all();
    $units = MeasurementUnit::all();
    file_put_contents($directory.'/productos.html', view('catalog.products.index', compact('errors', 'products', 'categories', 'units'))->render());
    auth()->forgetGuards();
    file_put_contents($directory.'/login.html', view('auth.login', compact('errors'))->render());
    echo json_encode(['connection' => $db->getName(), 'database' => $db->getDatabaseName(), 'static_preview_directory' => $directory, 'fixtures' => 'rolled back in finally', 'pages' => ['login.html', 'panel.html', 'productos.html']], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
}
