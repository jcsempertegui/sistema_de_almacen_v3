<?php

namespace App\Console\Commands;

use App\Models\BackupRun;
use App\Models\User;
use App\Services\DatabaseBackups;
use Illuminate\Console\Command;

class GenerateBackup extends Command
{
    protected $signature = 'sga:generate-backup {backup : Solicitud existente} {operator : Usuario responsable}';

    protected $description = 'Generar un respaldo solicitado, sin modificar el esquema ni borrar archivos';

    public function handle(): int
    {
        $actor = User::findOrFail($this->argument('operator'));
        $run = BackupRun::findOrFail($this->argument('backup'));
        $run = app(DatabaseBackups::class)->generate($actor, $run);
        $this->info('Respaldo #'.$run->id.' generado. SHA-256: '.$run->sha256);

        return self::SUCCESS;
    }
}
