<?php

// Read-only fingerprints, never SQL backups or row contents.
require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$settings = Dotenv\Dotenv::parse(file_get_contents($root.'/.env'));
if (($settings['DB_CONNECTION'] ?? '') !== 'mysql' || ($settings['DB_DATABASE'] ?? '') !== 'sga_rio_grande'
    || ! in_array($settings['DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
    || ! empty($settings['DB_URL']) || ! ctype_digit((string) ($settings['DB_PORT'] ?? '3306'))) {
    throw new RuntimeException('La configuración local requiere revisión antes de inspeccionar.');
}

$result = ['captured_at' => (new DateTimeImmutable('now', new DateTimeZone('America/La_Paz')))->format(DATE_ATOM), 'php' => PHP_VERSION, 'databases' => []];
$expectedMigrations = array_map(fn ($path) => basename($path, '.php'), glob($root.'/database/migrations/*.php'));
sort($expectedMigrations);
foreach (['sga_rio_grande', 'sga_rio_grande_testing'] as $database) {
    $pdo = new PDO('mysql:host='.$settings['DB_HOST'].';port='.($settings['DB_PORT'] ?? '3306').';dbname='.$database.';charset=utf8mb4', $settings['DB_USERNAME'] ?? 'root', $settings['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $identity = $pdo->query('SELECT DATABASE() AS database_name, @@hostname AS server, @@port AS port, @@datadir AS data_directory, VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
        if ($identity['database_name'] !== $database) {
            throw new RuntimeException('Destino de inspección incorrecto.');
        }
        $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        $fingerprints = [];
        foreach ($tables as $table) {
            $identifier = '`'.str_replace('`', '``', $table).'`';
            $ddl = $pdo->query('SHOW CREATE TABLE '.$identifier)->fetch(PDO::FETCH_NUM)[1];
            $rows = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $pdo->query('SELECT * FROM '.$identifier)->fetchAll(PDO::FETCH_ASSOC));
            sort($rows, SORT_STRING);
            $fingerprints[$table] = ['rows' => count($rows), 'data_sha256' => hash('sha256', implode("\n", $rows)), 'ddl_sha256' => hash('sha256', $ddl)];
        }
        $applied = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
        $batches = $pdo->query('SELECT batch, COUNT(*) AS migrations FROM migrations GROUP BY batch ORDER BY batch')->fetchAll(PDO::FETCH_ASSOC);
        $result['databases'][$database] = ['identity' => $identity, 'tables' => count($tables), 'migrations' => count($applied), 'pending' => array_values(array_diff($expectedMigrations, $applied)), 'unexpected_migrations' => array_values(array_diff($applied, $expectedMigrations)), 'batches' => $batches, 'fingerprints' => $fingerprints];
    } finally {
        $pdo->rollBack();
    }
}
$protected = ['.env', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json'];
foreach ($protected as $file) {
    if (is_file($root.'/'.$file)) {
        $result['protected_files'][$file] = hash_file('sha256', $root.'/'.$file);
    }
}
foreach (glob($root.'/database/migrations/*.php') as $file) {
    $result['migration_files'][basename($file)] = hash_file('sha256', $file);
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
