<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Credentials are read locally; the application database name is never reused.
if (is_file(dirname(__DIR__).'/bootstrap/cache/config.php')) {
    throw new RuntimeException('Las pruebas requieren configuración sin caché para garantizar el aislamiento.');
}

$settings = Dotenv\Dotenv::parse(file_get_contents(dirname(__DIR__).'/.env'));
// A fresh key is used only by this test process, never written to .env.
$testKey = 'base64:'.base64_encode(random_bytes(32));
$_ENV['APP_KEY'] = $_SERVER['APP_KEY'] = $testKey;
putenv('APP_KEY='.$testKey);
foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => ''] as $key => $default) {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? $settings[$key] ?? $default;
    $_ENV[$key] = $_SERVER[$key] = $value;
    putenv($key.'='.$value);
}
if (($_ENV['APP_ENV'] ?? '') !== 'testing' || ($_ENV['DB_DATABASE'] ?? '') !== 'sga_rio_grande_testing' || ($_ENV['DB_CONNECTION'] ?? '') !== 'mysql' || ! empty($_ENV['DB_URL'])) {
    throw new RuntimeException('Se rechazó una configuración de pruebas que no está aislada.');
}
$pdo = new PDO('mysql:host='.$_ENV['DB_HOST'].';port='.$_ENV['DB_PORT'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$exists = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'sga_rio_grande_testing'")->fetchColumn();
if (! $exists) {
    throw new RuntimeException('La base exclusiva de pruebas debe existir antes de ejecutar la suite.');
}
