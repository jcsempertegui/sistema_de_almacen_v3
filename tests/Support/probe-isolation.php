<?php

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;

// Read-only process probe. Never migrates or creates fixtures.
require dirname(__DIR__).'/bootstrap.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = $app->make('db')->connection();
TestingDatabaseGuard::assertConnection($connection);
echo json_encode(['connection' => $connection->getName(), 'identity' => TestingDatabaseGuard::identity($connection->getPdo())], JSON_THROW_ON_ERROR).PHP_EOL;
