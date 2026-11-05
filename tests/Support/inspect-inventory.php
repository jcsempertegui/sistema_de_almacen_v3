<?php

// Read-only evidence utility: the target is fixed and cannot be supplied by CLI.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'sga_rio_grande_testing', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}
require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = DB::connection();
if (! $app->environment('testing') || $db->getDatabaseName() !== 'sga_rio_grande_testing' || $db->selectOne('SELECT DATABASE() AS db')->db !== 'sga_rio_grande_testing') {
    throw new RuntimeException('Se rechazó una conexión no aislada.');
}
$db->statement('SET TRANSACTION READ ONLY');
$db->beginTransaction();
try {
    $resultado = ['database' => $db->selectOne('SELECT DATABASE() AS db')->db, 'php' => PHP_VERSION, 'laravel' => $app->version(), 'mariadb' => $db->selectOne('SELECT VERSION() AS v')->v, 'sql_mode' => $db->selectOne('SELECT @@sql_mode AS v')->v, 'tables' => [], 'applied' => $db->table('migrations')->orderBy('id')->pluck('migration')->all()];
    foreach ($db->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $row) {
        $table = array_values((array) $row)[0];
        $resultado['tables'][$table] = $db->table($table)->count();
    }
    $resultado['role_permissions'] = $db->table('roles')->select('codigo')->get()->mapWithKeys(fn ($rol) => [$rol->codigo => $db->table('permiso_rol')->join('roles', 'roles.id', '=', 'permiso_rol.rol_id')->where('roles.codigo', $rol->codigo)->where('permiso_rol.activo', true)->count()])->all();
    $resultado['inventory_constraints'] = $db->select("SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'inventory_%' OR TABLE_NAME IN ('existencias_ubicacion','posiciones_unidad_individual','productos_existencia_inicial')) ORDER BY TABLE_NAME, CONSTRAINT_NAME");
    $resultado['inventory_foreign_keys'] = $db->select("SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'inventory_%' OR TABLE_NAME IN ('existencias_ubicacion','posiciones_unidad_individual','productos_existencia_inicial')) ORDER BY TABLE_NAME, CONSTRAINT_NAME");
    echo json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} finally {
    $db->rollBack();
}
