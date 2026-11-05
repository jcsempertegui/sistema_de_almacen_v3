<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Connection;
use RuntimeException;

/** Capacidad temporal exclusiva de consola; no altera el aislamiento de PHPUnit. */
final class OperacionLocalAutorizada
{
    private static ?Connection $conexion = null;

    public static function vigente(Connection $conexion): bool
    {
        return PHP_SAPI === 'cli' && self::$conexion === $conexion;
    }

    public static function ejecutar(Connection $conexion, array $identidad, string $respaldo, string $sha256, Closure $operacion): mixed
    {
        $ruta = realpath($respaldo);
        $normalizar = fn (string $v) => strtolower(str_replace('\\', '/', $v));
        if (PHP_SAPI !== 'cli' || self::$conexion !== null || ! $ruta
            || str_starts_with($normalizar($ruta), rtrim($normalizar(base_path()), '/').'/')
            || filesize($ruta) < 1000 || ! preg_match('/^[a-f0-9]{64}$/D', $sha256)
            || ! hash_equals($sha256, hash_file('sha256', $ruta))) {
            throw new RuntimeException('Se requiere consola y un respaldo externo íntegro para la operación autorizada.');
        }
        $actual = TestingDatabaseGuard::identity($conexion->getPdo());
        $nombre = $actual['database_name'];
        if ($actual !== $identidad || ! ($nombre === 'sga_rio_grande' || preg_match('/^sga_rg_ensayo_espanol_[0-9]{8}$/D', $nombre))
            || ($nombre === 'sga_rio_grande' && ! app()->isDownForMaintenance())) {
            throw new RuntimeException('Destino no autorizado o aplicación principal fuera de mantenimiento.');
        }
        $llave = 'sga-renovacion-'.$nombre;
        if ((int) $conexion->selectOne('SELECT GET_LOCK(?, 0) AS obtenido', [$llave])->obtenido !== 1) {
            throw new RuntimeException('Otra operación administrativa utiliza este destino.');
        }
        self::$conexion = $conexion;
        try {
            return $operacion();
        } finally {
            self::$conexion = null;
            $conexion->selectOne('SELECT RELEASE_LOCK(?) AS liberado', [$llave]);
        }
    }
}
