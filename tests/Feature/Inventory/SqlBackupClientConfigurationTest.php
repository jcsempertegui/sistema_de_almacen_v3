<?php

namespace Tests\Feature\Inventory;

use App\Services\SqlBackupClient;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SqlBackupClientConfigurationTest extends TestCase
{
    private array $directories = [];

    private function directory(): string
    {
        $directory = sys_get_temp_dir().'/sga-client-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $this->directories[] = $directory;

        return $directory;
    }

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
        parent::tearDown();
    }

    private function client(string $directory, string $version): SqlBackupClient
    {
        foreach (['mysqldump', 'mysql'] as $name) {
            touch($directory.'/'.$name.(PHP_OS_FAMILY === 'Windows' ? '.exe' : ''));
        }
        config(['sga.mariadb_bin' => $directory, 'sga.mariadb_version' => '10.4']);

        return new class($version) extends SqlBackupClient
        {
            public function __construct(private string $version) {}

            protected function versionOutput(string $path): string
            {
                return $this->version;
            }
        };
    }

    public function test_an_explicit_directory_with_spaces_accepts_compatible_patch_versions(): void
    {
        $directory = $this->directory();
        $withSpaces = $directory.' client with spaces';
        rename($directory, $withSpaces);
        $this->directories = [$withSpaces];
        $inspection = $this->client($withSpaces, 'mysql Ver 15.1 Distrib 10.4.99-MariaDB, for Win64')->inspect();
        $this->assertSame('10.4.99', $inspection['dump']['version']);
        $this->assertSame(realpath($withSpaces), dirname($inspection['client']['path']));
        $this->assertCount(2, glob($withSpaces.'/*'));
    }

    public static function rejectedVersions(): array
    {
        return [['mysql Ver 8.0.41 for Win64'], ['mysql Ver 15.1 Distrib 10.3.39-MariaDB'], ['untrusted 10.4.32-MariaDB do-not-print']];
    }

    #[DataProvider('rejectedVersions')]
    public function test_other_vendors_series_and_unrecognized_output_are_rejected(string $version): void
    {
        $client = $this->client($this->directory(), $version);
        try {
            $client->inspect();
            $this->fail('Se aceptó una versión incompatible.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('serie MariaDB', $exception->getMessage());
            $this->assertStringNotContainsString('do-not-print', $exception->getMessage());
        }
    }

    public function test_an_invalid_explicit_directory_does_not_fall_back_to_another_installation(): void
    {
        config(['sga.mariadb_bin' => $this->directory().'/missing']);
        $this->expectExceptionMessage('directorio MariaDB configurado');
        (new SqlBackupClient)->inspect();
    }

    public function test_a_directory_without_clients_is_rejected(): void
    {
        config(['sga.mariadb_bin' => $this->directory()]);
        $this->expectExceptionMessage('No se encontró el cliente');
        (new SqlBackupClient)->inspect();
    }

    public function test_a_process_failure_is_reported_without_exposing_process_details(): void
    {
        $directory = $this->directory();
        $this->client($directory, 'unused');
        $process = \Mockery::mock(Process::class);
        $process->shouldReceive('mustRun')->once()->andThrow(new RuntimeException('private-process-details'));
        $client = new class($process) extends SqlBackupClient
        {
            public function __construct(private Process $process) {}

            protected function versionProcess(string $path): Process
            {
                return $this->process;
            }
        };
        try {
            $client->inspect();
            $this->fail('Se ignoró el fallo del proceso.');
        } catch (RuntimeException $exception) {
            $this->assertSame('No se pudo comprobar la versión del cliente MariaDB.', $exception->getMessage());
            $this->assertStringNotContainsString('private-process-details', $exception->getMessage());
        }
    }

    public function test_restoration_to_primary_or_testing_is_rejected_before_opening_any_file(): void
    {
        foreach (['sga_rio_grande', 'sga_rio_grande_testing', 'other_database'] as $database) {
            try {
                (new SqlBackupClient)->restore('does-not-exist', 'does-not-exist', $database, 'does-not-exist');
                $this->fail('Se permitió restaurar fuera de un ensayo.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('destino nuevo de ensayo', $exception->getMessage());
            }
        }
    }

    public function test_the_diagnostic_command_uses_only_inspection(): void
    {
        $client = $this->client($this->directory(), 'mysql Ver 15.1 Distrib 10.4.32-MariaDB');
        $this->app->instance(SqlBackupClient::class, $client);
        $this->artisan('sga:check-backup-client')->expectsOutputToContain('MariaDB 10.4.32')->assertSuccessful();
        $this->assertSame(0, DB::table('backup_runs')->count());
    }
}
