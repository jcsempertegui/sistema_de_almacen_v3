<?php

namespace App\Console\Commands;

use App\Services\ClienteRespaldoSql;
use Illuminate\Console\Command;

class VerificarClienteRespaldo extends Command
{
    protected $signature = 'sga:check-backup-client';

    protected $description = 'Comprobar rutas y versiones MariaDB sin respaldar ni restaurar';

    public function handle(ClienteRespaldoSql $client): int
    {
        try {
            foreach ($client->inspect() as $clase => $binary) {
                $this->line($clase.': '.$binary['path'].' (MariaDB '.$binary['version'].')');
            }
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
