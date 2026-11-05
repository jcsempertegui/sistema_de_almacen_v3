<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Services\RespaldosBaseDatos;
use Illuminate\Console\Command;

class EnsayarRestauracionRespaldo extends Command
{
    protected $signature = 'sga:restore-rehearsal {rehearsal} {operator} {--confirm-target= : Nombre exacto de la base nueva autorizada}';

    protected $description = 'Restaurar exclusivamente en un destino aislado nuevo, conservándolo para revisión';

    public function handle(): int
    {
        app(RespaldosBaseDatos::class)->restoreRehearsal(Usuario::findOrFail($this->argument('operator')), (int) $this->argument('rehearsal'), (string) $this->option('confirm-target'));
        $this->info('Ensayo verificado. La base aislada se conserva.');

        return self::SUCCESS;
    }
}
