<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Foundation\Application;
use PDO;
use RuntimeException;

final class TestingDatabaseGuard
{
    public const CONNECTION = 'sga_testing';

    public const DATABASE = 'sga_rio_grande_testing';

    private static function require(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Aislamiento de testing rechazado antes de escribir.');
        }
    }

    private static function path(string $path): string
    {
        $path = str_replace('\\', '/', realpath($path) ?: $path);

        return rtrim(PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path, '/');
    }

    public static function identity(PDO $pdo): array
    {
        $row = $pdo->query('SELECT DATABASE() AS database_name, @@hostname AS server, @@port AS port, @@datadir AS data_directory')->fetch(PDO::FETCH_ASSOC);
        $row['port'] = (int) $row['port'];
        $row['data_directory'] = self::path($row['data_directory']);

        return $row;
    }

    private static function connect(string $database, string $host, string $port, string $username, string $password): PDO
    {
        self::require(in_array($host, ['127.0.0.1', 'localhost', '::1'], true) && ctype_digit($port));

        return new PDO('mysql:host='.$host.';port='.$port.';dbname='.$database.';charset=utf8mb4', $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    }

    private static function export(string $key, string $value): void
    {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    public static function expected(): array
    {
        self::require(getenv('APP_ENV') === 'testing' && getenv('DB_CONNECTION') === 'mysql'
            && getenv('DB_DATABASE') === self::DATABASE && ! getenv('DB_URL'));
        $expected = json_decode(getenv('SGA_TEST_IDENTITY') ?: '', true);
        self::require(is_array($expected) && ($expected['database_name'] ?? '') === self::DATABASE
            && isset($expected['server'], $expected['port'], $expected['data_directory'], $expected['host'])
            && $expected['host'] === getenv('DB_HOST') && $expected['port'] === (int) getenv('DB_PORT'));

        return $expected;
    }

    // First process verifies the server against the configured primary using reads only.
    // Children inherit that pinned identity; they never rediscover/approve a destination.
    public static function prepare(array $settings): void
    {
        self::require(! is_file(dirname(__DIR__, 2).'/bootstrap/cache/config.php'));
        self::require(getenv('APP_ENV') === 'testing' && getenv('DB_CONNECTION') === 'mysql'
            && getenv('DB_DATABASE') === self::DATABASE && ! getenv('DB_URL'));
        foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_USERNAME' => 'root', 'DB_PASSWORD' => ''] as $key => $default) {
            $value = getenv($key);
            self::export($key, $value !== false ? $value : (string) ($settings[$key] ?? $default));
        }
        if (! getenv('SGA_TEST_IDENTITY')) {
            self::require(($settings['DB_CONNECTION'] ?? '') === 'mysql' && ($settings['DB_DATABASE'] ?? '') === 'sga_rio_grande' && empty($settings['DB_URL']));
            self::require(getenv('DB_HOST') === ($settings['DB_HOST'] ?? '127.0.0.1') && getenv('DB_PORT') === (string) ($settings['DB_PORT'] ?? '3306'));
            $primary = self::connect('sga_rio_grande', getenv('DB_HOST'), getenv('DB_PORT'), (string) ($settings['DB_USERNAME'] ?? 'root'), (string) ($settings['DB_PASSWORD'] ?? ''));
            $identity = self::identity($primary);
            self::require($identity['database_name'] === 'sga_rio_grande');
            $identity['database_name'] = self::DATABASE;
            $identity['host'] = getenv('DB_HOST');
            self::export('SGA_TEST_IDENTITY', json_encode($identity, JSON_THROW_ON_ERROR));
        }
        self::pdo();
    }

    public static function pdo(): PDO
    {
        $expected = self::expected();
        $pdo = self::connect(self::DATABASE, $expected['host'], (string) $expected['port'], getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
        self::assertIdentity(self::identity($pdo), $expected);

        return $pdo;
    }

    private static function assertIdentity(array $actual, array $expected): void
    {
        unset($expected['host']);
        self::require($actual === $expected);
    }

    public static function assertConnection(Connection $connection): void
    {
        $expected = self::expected();
        self::require($connection->getName() === self::CONNECTION
            && $connection->getConfig('name') === self::CONNECTION
            && $connection->getConfig('driver') === 'mysql'
            && $connection->getDatabaseName() === self::DATABASE
            && $connection->getConfig('host') === $expected['host']
            && (int) $connection->getConfig('port') === $expected['port']
            && ! $connection->getConfig('url') && ! $connection->getConfig('read')
            && ! $connection->getConfig('write') && ! $connection->getConfig('unix_socket'));
        self::assertIdentity(self::identity($connection->getPdo()), $expected);
    }

    public static function assertSql(string $sql): void
    {
        self::require(preg_match('/\b(?:USE\s|(?:CREATE|DROP|ALTER)\s+(?:DATABASE|SCHEMA)\b|sga_rio_grande\s*[`"\]]?\s*\.)/i', $sql) !== 1);
    }

    public static function install(Application $app): void
    {
        self::expected();
        $config = $app['config']['database.connections.mysql'];
        self::require(is_array($config) && ($config['database'] ?? '') === self::DATABASE && empty($config['url']));
        $config['name'] = self::CONNECTION;
        $manager = $app->make('db');
        foreach (array_keys($manager->getConnections()) as $name) {
            $manager->purge($name);
        }
        $app['config']->set('database.connections', [self::CONNECTION => $config]);
        $app['config']->set('database.default', self::CONNECTION);
        $app['events']->listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            // Install before rejecting: Laravel may already have cached this object.
            $event->connection->beforeExecuting(function (string $sql, array $bindings, Connection $connection): void {
                self::assertConnection($connection);
                self::assertSql($sql);
            });
            self::assertConnection($event->connection);
        });
        self::assertConnection($manager->connection());
    }

    public static function processEnvironment(): array
    {
        self::expected();
        $environment = [];
        foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'SGA_TEST_IDENTITY'] as $key) {
            $environment[$key] = getenv($key);
        }

        return $environment;
    }
}
