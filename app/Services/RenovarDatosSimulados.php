<?php

namespace App\Services;

use App\Models\Usuario;
use App\Support\EsquemaEspanol;
use App\Support\OperacionLocalAutorizada;
use App\Support\TestingDatabaseGuard;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reemplazo expresamente autorizado; no se expone como acción web ni seeder. */
final class RenovarDatosSimulados
{
    public const ORIGEN_AUDITORIA = 'console';

    public function ejecutar(Usuario $administrador, CarbonImmutable $hasta, ?Closure $progreso = null): array
    {
        $conexion = DB::connection();
        if (! OperacionLocalAutorizada::vigente($conexion)) {
            TestingDatabaseGuard::assertConnection($conexion);
        }
        if ((int) $conexion->selectOne('SELECT @@foreign_key_checks AS fk, @@check_constraint_checks AS ck')->fk !== 1
            || (int) $conexion->selectOne('SELECT @@check_constraint_checks AS ck')->ck !== 1) {
            throw new RuntimeException('Las restricciones deben permanecer activas.');
        }

        return DB::transaction(function () use ($administrador, $hasta, $progreso, $conexion) {
            app(ModificacionSegura::class)->exigirPermisos($administrador, 'users.create', 'users.assign_role', 'products.create', 'initial_stock.confirm');
            $administrador = Usuario::whereKey($administrador->id)->lockForUpdate()->firstOrFail();
            if (! $administrador->activo || ! app(ModificacionSegura::class)->hasAdministrativeAccess()) {
                throw new RuntimeException('No se puede perder el acceso administrativo.');
            }
            $usuarioAntes = $administrador->getAttributes();
            $tablas = array_map(fn ($r) => $r->TABLE_NAME, DB::select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"));
            $esperadas = array_values(EsquemaEspanol::diccionario()['tablas']);
            sort($tablas);
            sort($esperadas);
            if ($tablas !== $esperadas) {
                throw new RuntimeException('Tablas inesperadas; se cancela el reemplazo.');
            }
            $conservar = ['migrations', 'roles', 'permisos', 'permiso_rol', 'usuarios'];
            $huellas = [];
            foreach (array_diff($conservar, ['usuarios']) as $tabla) {
                $filas = DB::table($tabla)->get()->map(fn ($r) => json_encode($r, JSON_THROW_ON_ERROR))->sort()->values()->all();
                $huellas[$tabla] = hash('sha256', implode("\n", $filas));
            }
            $retiradas = [];
            foreach (array_diff($tablas, $conservar) as $tabla) {
                $retiradas[$tabla] = DB::table($tabla)->count();
            }
            // Romper exclusivamente esta referencia nullable permite retirar ciclos y
            // cierres en orden, manteniendo activas todas las FK y los CHECK.
            DB::table('items_devolucion_herramienta')->update(['ciclo_custodia_id' => null]);
            $pendientes = array_keys(array_filter($retiradas));
            while ($pendientes) {
                $avances = 0;
                foreach ($pendientes as $indice => $tabla) {
                    try {
                        $orden = $tabla === 'ubicaciones' ? ' ORDER BY id DESC' : '';
                        DB::delete('DELETE FROM `'.$tabla.'`'.$orden);
                        unset($pendientes[$indice]);
                        $avances++;
                    } catch (QueryException $e) {
                        if ((int) ($e->errorInfo[1] ?? 0) !== 1451) {
                            throw $e;
                        }
                    }
                }
                if (! $avances) {
                    throw new RuntimeException('Dependencias pendientes: '.implode(', ', $pendientes).'. Se revierte toda la operación.');
                }
            }
            $retiradas['usuarios'] = Usuario::whereKeyNot($administrador->id)->delete();
            $identidad = TestingDatabaseGuard::identity($conexion->getPdo());
            $resultado = app(EscenarioAnualPlanta::class)->run($administrador, $hasta, $identidad, $progreso, $identidad['database_name'] === 'sga_rio_grande');
            foreach ($huellas as $tabla => $huella) {
                $filas = DB::table($tabla)->get()->map(fn ($r) => json_encode($r, JSON_THROW_ON_ERROR))->sort()->values()->all();
                if (! hash_equals($huella, hash('sha256', implode("\n", $filas)))) {
                    throw new RuntimeException('Cambió la configuración protegida: '.$tabla);
                }
            }
            if (Usuario::findOrFail($administrador->id)->getAttributes() !== $usuarioAntes) {
                throw new RuntimeException('Cambió el acceso administrativo protegido.');
            }
            app(AuditoriaAdministrativa::class)->record(
                $administrador, 'datos_simulados.renovados', $administrador,
                ['registros_retirados' => $retiradas],
                ['periodo_desde' => $resultado['from'], 'periodo_hasta' => $resultado['through'], 'resumen' => $resultado['summary']],
                self::ORIGEN_AUDITORIA,
            );
            $resultado['registros_retirados'] = $retiradas;

            return $resultado;
        }, attempts: 1);
    }
}
