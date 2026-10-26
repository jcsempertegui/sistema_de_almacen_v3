<?php

use App\Support\TestingDatabaseGuard;

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
TestingDatabaseGuard::prepare($settings);
