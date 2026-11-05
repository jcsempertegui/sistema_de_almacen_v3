<?php

namespace Tests\Support;

use App\Support\EsquemaEspanol;
use App\Support\TestingDatabaseGuard;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Ensaya el algoritmo congelado contra el esquema traducido, sin editar el archivo histórico. */
final class CargadorMigracionArchivada
{
    public static function cargar(string $ruta): object
    {
        TestingDatabaseGuard::assertConnection(DB::connection());
        $huellas = json_decode(file_get_contents(database_path('huellas_migraciones_anteriores.json')), true, flags: JSON_THROW_ON_ERROR);
        $nombre = basename($ruta);
        if (realpath(dirname($ruta)) !== realpath(database_path('migrations')) || ! isset($huellas[$nombre]) || ! hash_equals($huellas[$nombre], hash_file('sha256', $ruta))) {
            throw new RuntimeException('Solo se permite cargar una migración archivada con su huella verificada.');
        }
        $plan = EsquemaEspanol::diccionario();
        $mapa = $plan['tablas'];
        $columnas = [];
        foreach ($plan['columnas'] as $tabla => $lista) {
            if (! in_array($tabla, $plan['excepciones_tecnicas'], true)) {
                $columnas = array_merge($columnas, $lista);
            }
        }
        $mapa = array_merge($mapa, $columnas);
        $traducir = static function (string $texto) use ($mapa, $columnas): string {
            if (isset($mapa[$texto])) {
                return $mapa[$texto];
            }
            if (preg_match('/^([a-z_]+)\s*\+\s*1$/D', $texto, $m) && isset($columnas[$m[1]])) {
                return $columnas[$m[1]].' + 1';
            }
            if (preg_match('/^([a-z_]+)\.([a-z_]+)$/D', $texto, $m) && isset($columnas[$m[2]])) {
                return ($mapa[$m[1]] ?? $m[1]).'.'.$columnas[$m[2]];
            }
            if (str_contains($texto, '->')) {
                [$base, $camino] = explode('->', $texto, 2);
                if (isset($columnas[$base])) {
                    return $columnas[$base].'->'.$camino;
                }
            }

            return $texto;
        };
        $tokens = token_get_all(file_get_contents($ruta), TOKEN_PARSE);
        $codigo = '';
        foreach ($tokens as $indice => $token) {
            if (! is_array($token)) {
                $codigo .= $token;

                continue;
            }
            [$tipo, $texto] = $token;
            if ($tipo === T_OPEN_TAG) {
                continue;
            }
            if ($tipo === T_CONSTANT_ENCAPSED_STRING) {
                $valor = $texto[0] === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], substr($texto, 1, -1)) : stripcslashes(substr($texto, 1, -1));
                $nuevo = $traducir($valor);
                $codigo .= $nuevo === $valor ? $texto : var_export($nuevo, true);

                continue;
            }
            if ($tipo === T_STRING && isset($columnas[$texto])) {
                $previo = $indice - 1;
                $siguiente = $indice + 1;
                while ($previo >= 0 && is_array($tokens[$previo]) && $tokens[$previo][0] === T_WHITESPACE) {
                    $previo--;
                }
                while (isset($tokens[$siguiente]) && is_array($tokens[$siguiente]) && $tokens[$siguiente][0] === T_WHITESPACE) {
                    $siguiente++;
                }
                if (is_array($tokens[$previo] ?? null) && in_array($tokens[$previo][0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true) && ($tokens[$siguiente] ?? null) !== '(') {
                    $texto = $columnas[$texto];
                }
            }
            $codigo .= $texto;
        }

        return eval($codigo);
    }
}
