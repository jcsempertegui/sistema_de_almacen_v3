<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AnnualPlantScenario;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LoadAnnualPlantScenario extends Command
{
    protected $signature = 'sga:load-annual-scenario
        {--database= : Nombre exacto del destino}
        {--server= : Hostname SQL verificado}
        {--port= : Puerto SQL verificado}
        {--datadir= : Directorio SQL verificado}
        {--administrator= : Correo del administrador existente}
        {--through= : Último día del año simulado, AAAA-MM-DD}
        {--allow-primary : Autorización explícita para la principal local}
        {--backup-file= : Respaldo SQL nuevo, fuera del repositorio}
        {--backup-sha256= : SHA-256 previamente verificado del respaldo}';

    protected $description = 'Cargar una sola simulación anual autorizada, conservando el administrador y usando las acciones del sistema';

    public function handle(AnnualPlantScenario $scenario): int
    {
        $database = (string) $this->option('database');
        $path = strtolower(rtrim(str_replace('\\', '/', (string) $this->option('datadir')), '/'));
        $expected = ['database_name' => $database, 'server' => (string) $this->option('server'), 'port' => (int) $this->option('port'), 'data_directory' => $path];
        $actual = TestingDatabaseGuard::identity(DB::connection()->getPdo());
        $this->line(json_encode($actual, JSON_UNESCAPED_SLASHES));
        if ($actual !== $expected) {
            throw new RuntimeException('La instancia, puerto, directorio o base no coinciden; no se escribió nada.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) $this->option('through'))) {
            throw new RuntimeException('Indica --through=AAAA-MM-DD.');
        }
        $end = CarbonImmutable::createFromFormat('!Y-m-d', $this->option('through'), 'America/La_Paz');
        if ($end->format('Y-m-d') !== $this->option('through')) {
            throw new RuntimeException('La fecha no es válida.');
        }
        if ($database === 'sga_rio_grande') {
            $backup = realpath((string) $this->option('backup-file'));
            $workspace = strtolower(str_replace('\\', '/', base_path())).'/';
            if (! $this->option('allow-primary') || ! $backup || str_starts_with(strtolower(str_replace('\\', '/', $backup)), $workspace)
                || filesize($backup) < 1000 || ! hash_equals((string) $this->option('backup-sha256'), hash_file('sha256', $backup))) {
                throw new RuntimeException('La principal requiere autorización y respaldo externo íntegro.');
            }
        }
        $administrator = User::where('email', $this->option('administrator'))->where('is_active', true)->firstOrFail();
        $result = $scenario->run($administrator, $end, $expected, fn ($month) => $this->line('Mes preparado: '.$month), (bool) $this->option('allow-primary'));
        $this->info('Simulación confirmada. Las credenciales nuevas se muestran una sola vez:');
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
