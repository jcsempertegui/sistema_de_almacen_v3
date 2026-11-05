<?php

namespace App\Support;

use Illuminate\Database\Connection;
use RuntimeException;

final class EsquemaEspanol
{
    public static function diccionario(): array
    {
        return json_decode(file_get_contents(database_path('esquema_espanol.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    private static function identificador(string $nombre): string
    {
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $nombre)) {
            throw new RuntimeException('Identificador SQL no permitido.');
        }

        return '`'.$nombre.'`';
    }

    private static function traducirSql(string $sql, array $mapa): string
    {
        return preg_replace_callback("/'(?:''|\\\\.|[^'])*'|`([a-zA-Z_][a-zA-Z0-9_]*)`|\\b([a-zA-Z_][a-zA-Z0-9_]*)\\b/", function ($m) use ($mapa) {
            if (str_starts_with($m[0], "'")) {
                return $m[0];
            }
            $original = $m[1] ?: ($m[2] ?? '');
            $nuevo = $mapa[$original] ?? $original;

            return str_starts_with($m[0], '`') ? self::identificador($nuevo) : $nuevo;
        }, $sql);
    }

    private static function huellas(Connection $db, array $tablas, array $columnas, bool $traducido): array
    {
        $huellas = [];
        foreach ($tablas as $anterior => $nuevo) {
            if ($anterior === 'migrations') {
                continue;
            }
            $seleccion = [];
            foreach ($columnas[$anterior] as $columna => $destino) {
                $seleccion[] = self::identificador($traducido ? $destino : $columna).' AS '.self::identificador($columna);
            }
            $filas = array_map(fn ($fila) => json_encode((array) $fila, JSON_THROW_ON_ERROR), $db->select('SELECT '.implode(', ', $seleccion).' FROM '.self::identificador($traducido ? $nuevo : $anterior)));
            sort($filas, SORT_STRING);
            $huellas[$anterior] = ['filas' => count($filas), 'sha256' => hash('sha256', implode("\n", $filas))];
        }

        return $huellas;
    }

    public static function aplicar(Connection $db): void
    {
        // PHPUnit sigue sujeto a su guardia; el despliegue exige capacidad CLI temporal.
        if (! OperacionLocalAutorizada::vigente($db)) {
            TestingDatabaseGuard::assertConnection($db);
        }
        $identidad = $db->selectOne('SELECT DATABASE() AS base, @@hostname AS servidor, @@port AS puerto, @@datadir AS directorio');
        if ($identidad->base !== TestingDatabaseGuard::DATABASE && ! OperacionLocalAutorizada::vigente($db)) {
            throw new RuntimeException('La traducción solo está habilitada para el ensayo aislado de testing.');
        }
        $plan = self::diccionario();
        $tablas = $plan['tablas'];
        $columnas = $plan['columnas'];
        $estructuras = json_decode(file_get_contents(database_path('estructura_anterior.json')), true, flags: JSON_THROW_ON_ERROR);
        $actuales = array_map(fn ($r) => $r->TABLE_NAME, $db->select("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME"));
        $esperadas = array_keys($tablas);
        sort($esperadas);
        sort($actuales);
        if ($actuales !== $esperadas) {
            throw new RuntimeException('El esquema no coincide con el origen aprobado. Se detiene sin intentar reparar DDL parcial.');
        }
        foreach ($columnas as $tabla => $mapa) {
            $reales = array_map(fn ($r) => $r->COLUMN_NAME, $db->select('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION', [$tabla]));
            if ($reales !== array_keys($mapa)) {
                throw new RuntimeException('Columnas inesperadas en '.$tabla.'.');
            }
        }
        $antes = self::huellas($db, $tablas, $columnas, false);
        $claves = [];
        $comprobaciones = [];
        $definiciones = [];
        foreach ($estructuras as $tabla => $estructura) {
            // Compare only structural definitions; AUTO_INCREMENT may differ by data.
            $ddl = $db->selectOne('SHOW CREATE TABLE '.self::identificador($tabla));
            $texto = array_values((array) $ddl)[1];
            $normalizar = fn ($v) => preg_replace('/ AUTO_INCREMENT=\d+/', '', $v);
            if ($normalizar($texto) !== $normalizar($estructura['ddl'])) {
                throw new RuntimeException('Estructura distinta de la aprobada en '.$tabla.'.');
            }
            foreach (explode("\n", $texto) as $linea) {
                if (preg_match('/^\s+CONSTRAINT `([^`]+)` FOREIGN KEY (.+?)(?:,)?$/', $linea, $m)) {
                    $claves[$tabla][$m[1]] = rtrim(trim(substr($linea, strpos($linea, 'CONSTRAINT'))), ',');
                }
                if (preg_match('/^\s+`([^`]+)` (.+?)(?:,)?$/', $linea, $m)) {
                    $definiciones[$tabla][$m[1]] = preg_replace('/\s+CHECK\s+\(json_valid\(`[^`]+`\)\)$/i', '', rtrim($m[2], ','));
                }
            }
            $comprobaciones[$tabla] = $db->select('SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?', [$tabla]);
        }
        // Evidence is written before the first DDL statement; no row data is exported.
        $directorio = storage_path('app/private/traduccion');
        if (! is_dir($directorio) && ! mkdir($directorio, 0700, true)) {
            throw new RuntimeException('No se pudo preparar la evidencia del ensayo.');
        }
        $evidencia = ['identidad' => $identidad, 'antes' => $antes, 'resultado' => 'iniciado'];
        file_put_contents($directorio.'/ensayo.json', json_encode($evidencia, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        foreach ($claves as $tabla => $lista) {
            foreach ($lista as $nombre => $definicion) {
                $db->statement('ALTER TABLE '.self::identificador($tabla).' DROP FOREIGN KEY '.self::identificador($nombre));
            }
        }
        foreach ($comprobaciones as $tabla => $lista) {
            foreach ($lista as $restriccion) {
                $db->statement('ALTER TABLE '.self::identificador($tabla).' DROP CONSTRAINT '.self::identificador($restriccion->CONSTRAINT_NAME));
            }
        }
        foreach ($columnas as $tabla => $mapa) {
            $cambios = [];
            foreach ($mapa as $anterior => $nuevo) {
                if ($anterior !== $nuevo) {
                    $cambios[] = 'CHANGE COLUMN '.self::identificador($anterior).' '.self::identificador($nuevo).' '.self::traducirSql($definiciones[$tabla][$anterior], $mapa);
                }
            }
            if ($cambios) {
                $db->statement('ALTER TABLE '.self::identificador($tabla).' '.implode(', ', $cambios));
            }
        }
        $renombrados = [];
        foreach ($tablas as $anterior => $nuevo) {
            if ($anterior !== $nuevo) {
                $renombrados[] = self::identificador($anterior).' TO '.self::identificador($nuevo);
            }
        }
        $db->statement('RENAME TABLE '.implode(', ', $renombrados));
        $mapaGlobal = $tablas;
        foreach ($columnas as $tabla => $mapa) {
            if (! in_array($tabla, $plan['excepciones_tecnicas'], true)) {
                $mapaGlobal = array_merge($mapaGlobal, $mapa);
            }
        }
        foreach ($claves as $tabla => $lista) {
            foreach ($lista as $definicion) {
                $db->statement('ALTER TABLE '.self::identificador($tablas[$tabla]).' ADD '.self::traducirSql($definicion, $mapaGlobal));
            }
        }
        foreach ($comprobaciones as $tabla => $lista) {
            foreach ($lista as $restriccion) {
                $db->statement('ALTER TABLE '.self::identificador($tablas[$tabla]).' ADD CONSTRAINT '.self::identificador($restriccion->CONSTRAINT_NAME).' CHECK ('.self::traducirSql($restriccion->CHECK_CLAUSE, $columnas[$tabla]).')');
            }
        }
        $despues = self::huellas($db, $tablas, $columnas, true);
        $evidencia['despues'] = $despues;
        $evidencia['resultado'] = $antes === $despues ? 'conservacion_verificada' : 'diferencia_detectada';
        file_put_contents($directorio.'/ensayo.json', json_encode($evidencia, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if ($antes !== $despues) {
            throw new RuntimeException('El ensayo detectó diferencias de datos. Revisar la evidencia; no continuar el despliegue.');
        }
    }
}
