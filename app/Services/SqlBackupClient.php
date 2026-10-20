<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class SqlBackupClient
{
    private function binary(bool $dump): string
    {
        $name = PHP_OS_FAMILY === 'Windows' ? ($dump ? 'mysqldump.exe' : 'mysql.exe') : ($dump ? 'mariadb-dump' : 'mariadb');
        $path = rtrim(config('sga.mariadb_bin'), '/\\').'/'.$name;
        if (! is_file($path)) {
            throw new \RuntimeException('No se encontró el cliente MariaDB configurado.');
        }$p = new Process([$path, '--version']);
        $p->mustRun();
        if (! str_contains($p->getOutput(), '10.4.32-MariaDB')) {
            throw new \RuntimeException('El cliente debe ser MariaDB 10.4.32.');
        }

        return $path;
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
