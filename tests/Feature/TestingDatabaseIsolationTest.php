<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TestingDatabaseGuard;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TestingDatabaseIsolationTest extends TestCase
{
    public function test_factories_and_artisan_share_only_the_verified_testing_connection(): void
    {
        $this->assertSame([TestingDatabaseGuard::CONNECTION], array_keys(config('database.connections')));
        $user = User::factory()->create();
        $this->assertSame(TestingDatabaseGuard::CONNECTION, $user->getConnection()->getName());
        $this->assertTrue(DB::table('users')->where('id', $user->id)->exists());
        $this->assertSame(0, Artisan::call('migrate:status'));
    }

    public function test_a_factory_cannot_reuse_the_old_mysql_alias_even_if_it_points_to_testing(): void
    {
        $before = DB::table('users')->count();
        $config = config('database.connections.'.TestingDatabaseGuard::CONNECTION);
        $config['name'] = 'mysql';
        config(['database.connections.mysql' => $config]);
        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    User::factory()->connection('mysql')->create();
                    $this->fail('Se permitió reutilizar el alias histórico.');
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('Aislamiento', $exception->getMessage());
                    $this->assertSame($before, DB::table('users')->count());
                }
                // If a regression accepts the retry, it can only touch the test transaction.
                DB::getConnections()['mysql']->setPdo(DB::connection()->getPdo());
            }
        } finally {
            DB::purge('mysql');
            config(['database.connections' => [TestingDatabaseGuard::CONNECTION => config('database.connections.'.TestingDatabaseGuard::CONNECTION)]]);
        }
    }

    public function test_a_primary_configuration_is_rejected_without_opening_its_pdo(): void
    {
        $opened = false;
        $connection = new MySqlConnection(function () use (&$opened) {
            $opened = true;
            throw new \LogicException('Este PDO nunca debe abrirse.');
        }, 'sga_rio_grande', '', ['name' => 'mysql', 'driver' => 'mysql']);
        try {
            TestingDatabaseGuard::assertConnection($connection);
            $this->fail('Se permitió una configuración principal.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Aislamiento', $exception->getMessage());
            $this->assertFalse($opened);
        }
    }

    public static function forbiddenSql(): array
    {
        return [['USE sga_rio_grande'], ['CREATE DATABASE forbidden_test_destination'], ['DROP DATABASE forbidden_test_destination'], ['UPDATE `sga_rio_grande`.`users` SET is_active=0']];
    }

    #[DataProvider('forbiddenSql')]
    public function test_unsafe_sql_is_rejected_before_being_sent_to_the_server(string $sql): void
    {
        // Classify strings only: never send a dangerous probe to any real PDO.
        $this->expectExceptionMessage('Aislamiento');
        TestingDatabaseGuard::assertSql($sql);
    }

    public function test_the_query_hook_blocks_a_harmless_reference_to_the_primary(): void
    {
        $executed = false;
        DB::listen(function () use (&$executed) {
            $executed = true;
        });
        try {
            DB::select("SELECT 'sga_rio_grande.users' AS forbidden_reference");
            $this->fail('Se envió SQL fuera del esquema testing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Aislamiento', $exception->getMessage());
            $this->assertFalse($executed);
        }
    }

    public static function workers(): array
    {
        return array_map(fn ($name) => [$name], ['administration', 'location', 'product', 'individual-unit', 'inventory', 'worker-operation', 'correction']);
    }

    #[DataProvider('workers')]
    public function test_each_worker_rejects_a_wrong_database_before_loading_jobs(string $worker): void
    {
        $process = $this->probe('tests/Support/concurrent-'.$worker.'.php', ['DB_DATABASE' => 'sga_rio_grande']);
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Aislamiento', $process->getErrorOutput().$process->getOutput());
        $this->assertStringNotContainsString('READY', $process->getOutput());
    }

    public static function drifts(): array
    {
        return [['DB_HOST'], ['DB_PORT'], ['DB_URL'], ['server'], ['data_directory']];
    }

    #[DataProvider('drifts')]
    public function test_subprocesses_reject_endpoint_and_server_identity_drift(string $field): void
    {
        $overrides = [];
        if ($field === 'server' || $field === 'data_directory') {
            $identity = TestingDatabaseGuard::expected();
            $identity[$field] = 'not-the-verified-server';
            $overrides['SGA_TEST_IDENTITY'] = json_encode($identity, JSON_THROW_ON_ERROR);
        } else {
            $overrides[$field] = match ($field) {
                'DB_HOST' => '192.0.2.1',
                'DB_PORT' => '1',
                'DB_URL' => 'mysql://forbidden.invalid/sga_rio_grande',
            };
        }
        $process = $this->probe('tests/Support/probe-isolation.php', $overrides);
        $this->assertFalse($process->isSuccessful());
        $this->assertStringContainsString('Aislamiento', $process->getErrorOutput().$process->getOutput());
    }

    private function probe(string $script, array $overrides): Process
    {
        $environment = array_replace(TestingDatabaseGuard::processEnvironment(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => TestingDatabaseGuard::DATABASE, 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'], $overrides);
        $process = new Process([PHP_BINARY, base_path($script), '{}'], base_path(), $environment, null, 10);
        $process->run();

        return $process;
    }
}
