<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Services\GraficasPanel;
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
    $usuario = Usuario::factory()->create(['rol_id' => 1, 'nombre' => 'Operador de demostración', 'correo' => 'visual@example.test']);
    auth()->setUser($usuario);
    $request = Request::create('http://127.0.0.1:8010/panel.html');
    $dashboardRoute = app('router')->getRoutes()->getByName('dashboard')->bind($request);
    $request->setRouteResolver(fn () => $dashboardRoute);
    $app->instance('request', $request);
    app('url')->setRequest($request);
    $errors = new ViewErrorBag;
    $charts = app(GraficasPanel::class)->paraUsuario($usuario);
    file_put_contents($directory.'/panel.html', view('panel', compact('errors', 'charts'))->render());
    $categoria = Categoria::factory()->create(['nombre' => 'Herramientas y materiales']);
    $unidad = UnidadMedida::factory()->create(['nombre' => 'Unidad', 'simbolo' => 'und']);
    foreach (['Llave combinada 24 mm', 'Guantes de seguridad', 'Válvula de compuerta'] as $index => $nombre) {
        Producto::factory()->create(['nombre' => $nombre, 'codigo_interno' => 'VISUAL-'.($index + 1), 'categoria_id' => $categoria->id, 'unidad_medida_id' => $unidad->id]);
    }
    $productsRoute = app('router')->getRoutes()->getByName('productos.index')->bind($request);
    $request->setRouteResolver(fn () => $productsRoute);
    $productos = Producto::with(['categoria', 'unidadMedida'])->paginate(20);
    $categorias = Categoria::all();
    $unidades = UnidadMedida::all();
    file_put_contents($directory.'/productos.html', view('catalogo.productos.index', compact('errors', 'productos', 'categorias', 'unidades'))->render());
    auth()->forgetGuards();
    file_put_contents($directory.'/login.html', view('auth.login', compact('errors'))->render());
    echo json_encode(['connection' => $db->getName(), 'database' => $db->getDatabaseName(), 'static_preview_directory' => $directory, 'fixtures' => 'rolled back in finally', 'pages' => ['login.html', 'panel.html', 'productos.html']], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    $db->rollBack();
}
