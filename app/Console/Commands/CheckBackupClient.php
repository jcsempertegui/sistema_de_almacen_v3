<?php

namespace App\Console\Commands;

use App\Services\SqlBackupClient;
use Illuminate\Console\Command;

class CheckBackupClient extends Command
{
    protected $signature = 'sga:check-backup-client';

    protected $description = 'Comprobar rutas y versiones MariaDB sin respaldar ni restaurar';

    public function handle(SqlBackupClient $client): int
    {
        try {
            foreach ($client->inspect() as $kind => $binary) {
                $this->line($kind.': '.$binary['path'].' (MariaDB '.$binary['version'].')');
            }
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
