<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class SqlBackupClient
{
    protected function directories(): array
    {
        $configured = config('sga.mariadb_bin');
        if ($configured !== null && $configured !== '') {
            if (! is_string($configured) || preg_match('/[\x00\r\n]/', $configured) || realpath($configured) === false || ! is_dir($configured)) {
                throw new \RuntimeException('El directorio MariaDB configurado no existe o no es válido.');
            }

            return [realpath($configured)];
        }

        $paths = [];
        if (PHP_OS_FAMILY === 'Windows') {
            $paths[] = substr(PHP_BINARY, 0, 2).'/XAMPP/mysql/bin';
            $paths[] = (getenv('SystemDrive') ?: 'C:').'/xampp/mysql/bin';
        } else {
            $paths[] = '/usr/bin';
        }

        return array_values(array_unique(array_filter([...$paths, ...explode(PATH_SEPARATOR, getenv('PATH') ?: '')])));
    }

    protected function versionOutput(string $path): string
    {
        try {
            $process = $this->versionProcess($path);
            $process->mustRun();

            return $process->getOutput();
        } catch (\Throwable) {
            throw new \RuntimeException('No se pudo comprobar la versión del cliente MariaDB.');
        }
    }

    protected function versionProcess(string $path): Process
    {
        return new Process([$path, '--version'], null, null, null, 10);
    }

    private function resolve(bool $dump): array
    {
        $names = $dump ? ['mariadb-dump', 'mysqldump'] : ['mariadb', 'mysql'];
        $required = config('sga.mariadb_version');
        if (! is_string($required) || preg_match('/^\d+\.\d+$/D', $required) !== 1) {
            throw new \RuntimeException('Configura la serie MariaDB admitida como mayor.menor.');
        }
        foreach ($this->directories() as $directory) {
            foreach ($names as $name) {
                $path = rtrim($directory, '/\\').DIRECTORY_SEPARATOR.$name.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');
                if (! is_file($path) || realpath($path) === false) {
                    continue;
                }
                $path = realpath($path);
                $output = $this->versionOutput($path);
                if (preg_match('/\b(?:Distrib\s+|from\s+)(\d+\.\d+\.\d+)-MariaDB\b/i', $output, $matches) !== 1
                    || ! str_starts_with($matches[1], $required.'.')) {
                    throw new \RuntimeException('El cliente debe pertenecer a la serie MariaDB configurada.');
                }

                return ['path' => $path, 'version' => $matches[1]];
            }
        }

        throw new \RuntimeException('No se encontró el cliente MariaDB; configura SGA_MARIADB_BIN en el proceso.');
    }

    // Only executes --version. Does not connect, create SQL or restore data.
    public function inspect(): array
    {
        return ['dump' => $this->resolve(true), 'client' => $this->resolve(false)];
    }

    private function binary(bool $dump): string
    {
        return $this->resolve($dump)['path'];
    }

    public function dump(string $options, string $sql, string $database, string $logs): void
    {
        $p = new Process([$this->binary(true), '--defaults-file='.$options, '--single-transaction', '--quick', '--routines', '--events', '--triggers', '--hex-blob', '--default-character-set=utf8mb4', '--skip-lock-tables', '--skip-add-drop-table', '--skip-add-locks', '--skip-disable-keys', '--complete-insert', '--skip-extended-insert', '--comments', '--result-file='.$sql, $database], base_path(), null, null, (float) config('sga.backup_timeout'));
        $p->run();
        file_put_contents($logs, $p->getErrorOutput());
        if (! $p->isSuccessful()) {
            throw new \RuntimeException('Falló el volcado SQL; revisa el registro privado.');
        }
    }

    public function restore(string $options, string $sql, string $database, string $logs): void
    {
        if (preg_match('/^sga_rio_grande_rehearsal_[a-f0-9]{16}$/D', $database) !== 1) {
            throw new \RuntimeException('Solo se permite restaurar en un destino nuevo de ensayo autorizado.');
        }
        $input = fopen($sql, 'rb');
        try {
            $p = new Process([$this->binary(false), '--defaults-file='.$options, '--default-character-set=utf8mb4', '--binary-mode', $database], base_path(), null, $input, (float) config('sga.backup_timeout'));
            $p->run();
            file_put_contents($logs, $p->getErrorOutput());
            if (! $p->isSuccessful()) {
                throw new \RuntimeException('Falló el ensayo SQL; conserva el destino para revisar.');
            }
        } finally {
            fclose($input);
        }
    }
}
