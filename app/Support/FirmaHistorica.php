<?php

namespace App\Support;

/** Conserva los nombres y el orden del contrato JSON firmado antes de la traducción. */
final class FirmaHistorica
{
    public static function campos(string $tablaAnterior, array|object|null $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        $nombres = array_flip(EsquemaEspanol::diccionario()['columnas'][$tablaAnterior])
            + ['version_bloqueo' => 'lock_version']; // Campo opcional del contrato, aun cuando no existe f?sicamente.
        $resultado = [];
        foreach ((array) $datos as $nombre => $valor) {
            $resultado[$nombres[$nombre] ?? $nombre] = $valor;
        }

        return $resultado;
    }
}
