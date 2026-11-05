<?php

namespace App\Console\Commands;

use App\Models\EjecucionRespaldo;
use App\Models\Usuario;
use App\Services\RespaldosBaseDatos;
use Illuminate\Console\Command;

class GenerarRespaldo extends Command
{
    protected $signature = 'sga:generate-backup {backup : Solicitud existente} {operator : Usuario responsable}';

    protected $description = 'Generar un respaldo solicitado, sin modificar el esquema ni borrar archivos';

    public function handle(): int
    {
        $actor = Usuario::findOrFail($this->argument('operator'));
        $run = EjecucionRespaldo::findOrFail($this->argument('backup'));
        $run = app(RespaldosBaseDatos::class)->generate($actor, $run);
        $this->info('Respaldo #'.$run->id.' generado. SHA-256: '.$run->sha256);

        return self::SUCCESS;
    }
}
