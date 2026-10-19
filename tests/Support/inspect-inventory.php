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
    $result = ['database' => $db->selectOne('SELECT DATABASE() AS db')->db, 'php' => PHP_VERSION, 'laravel' => $app->version(), 'mariadb' => $db->selectOne('SELECT VERSION() AS v')->v, 'sql_mode' => $db->selectOne('SELECT @@sql_mode AS v')->v, 'tables' => [], 'applied' => $db->table('migrations')->orderBy('id')->pluck('migration')->all()];
    foreach ($db->select("SHOW FULL TABLES WHERE Table_type='BASE TABLE'") as $row) {
        $table = array_values((array) $row)[0];
        $result['tables'][$table] = $db->table($table)->count();
    }
    $result['role_permissions'] = $db->table('roles')->select('code')->get()->mapWithKeys(fn ($role) => [$role->code => $db->table('permission_role')->join('roles', 'roles.id', '=', 'permission_role.role_id')->where('roles.code', $role->code)->where('permission_role.is_active', true)->count()])->all();
    $result['inventory_constraints'] = $db->select("SELECT TABLE_NAME, CONSTRAINT_NAME, CONSTRAINT_TYPE FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'inventory_%' OR TABLE_NAME IN ('stock_positions','individual_unit_positions','initial_stock_products')) ORDER BY TABLE_NAME, CONSTRAINT_NAME");
    $result['inventory_foreign_keys'] = $db->select("SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, UPDATE_RULE, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND (TABLE_NAME LIKE 'inventory_%' OR TABLE_NAME IN ('stock_positions','individual_unit_positions','initial_stock_products')) ORDER BY TABLE_NAME, CONSTRAINT_NAME");
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} finally {
    $db->rollBack();
}
