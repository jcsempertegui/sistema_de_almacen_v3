<?php

namespace App\Console\Commands;

use App\Support\OperacionLocalAutorizada;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class TraducirEsquema extends Command
{
    protected $signature = 'sga:traducir-esquema
        {--base= : Nombre exacto de la base conectada}
        {--servidor= : Nombre verificado del servidor SQL}
        {--puerto= : Puerto SQL verificado}
        {--directorio= : Directorio de datos verificado}
        {--respaldo= : Respaldo SQL completo fuera del repositorio}
        {--sha256= : SHA-256 verificado del respaldo}';

    protected $description = 'Aplicar exclusivamente la traducción del esquema con identidad y respaldo explícitos';

    public function handle(): int
    {
        $identidad = [
            'database_name' => (string) $this->option('base'),
            'server' => (string) $this->option('servidor'),
            'port' => (int) $this->option('puerto'),
            'data_directory' => strtolower(rtrim(str_replace('\\', '/', (string) $this->option('directorio')), '/')),
        ];

        return OperacionLocalAutorizada::ejecutar(DB::connection(), $identidad, (string) $this->option('respaldo'), (string) $this->option('sha256'), function () {
            $resultado = Artisan::call('migrate', [
                '--path' => 'database/migrations/2026_11_01_000001_traducir_esquema_del_sga.php',
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());

            return $resultado;
        });
    }
}
