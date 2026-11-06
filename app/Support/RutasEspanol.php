<?php

namespace App\Support;

final class RutasEspanol
{
    public const OPERACIONES = ['deliveries' => 'entregas', 'loans' => 'prestamos', 'returns' => 'devoluciones'];

    public const REPORTES = ['stock' => 'existencias', 'movements' => 'movimientos', 'availability' => 'disponibilidad', 'remittances' => 'remitos', 'deliveries' => 'entregas', 'responsibilities' => 'responsabilidades', 'loans' => 'prestamos', 'returns' => 'devoluciones', 'corrections' => 'correcciones', 'audit' => 'auditoria'];

    public const CORRECCIONES = ['adjustment' => 'ajustes', 'compensation' => 'compensaciones'];

    public const ACCIONES = ['request' => 'solicitar', 'approve' => 'autorizar', 'reject' => 'rechazar', 'revoke' => 'revocar', 'cancel' => 'cancelar', 'execute' => 'ejecutar'];

    private const FILTROS = ['q' => 'buscar', 'pending' => 'pendientes', 'from' => 'desde', 'to' => 'hasta', 'format' => 'formato', 'page' => 'pagina', 'units_page' => 'pagina_unidades', 'roots_only' => 'solo_raices', 'custody' => 'custodia'];

    private const VALORES = [
        'estado' => ['active' => 'activo', 'inactive' => 'inactivo', 'draft' => 'borrador', 'confirmed' => 'confirmado', 'cancelled' => 'cancelado', 'requested' => 'solicitado', 'authorized' => 'autorizado', 'rejected' => 'rechazado', 'executed' => 'ejecutado'],
        'tipo' => ['opening' => 'apertura', 'receipt' => 'ingreso', 'issue' => 'egreso', 'adjustment' => 'ajuste', 'compensation' => 'compensacion', 'shelf' => 'estante', 'cabinet' => 'gabinete'],
        'tipo_dato' => ['text' => 'texto', 'boolean' => 'booleano', 'date' => 'fecha'],
        'modo_control' => ['quantity' => 'cantidad'],
        'format' => ['print' => 'imprimir'],
        'custody' => ['pending' => 'pendiente', 'overdue' => 'vencido', 'returned' => 'devuelto'],
    ];

    public static function consultaPublica(array $consulta): array
    {
        $resultado = [];
        foreach ($consulta as $clave => $valor) {
            $claveInterna = self::interno((string) $clave, self::FILTROS);
            if (is_string($valor)) {
                $valor = (self::VALORES[$claveInterna] ?? [])[$valor] ?? $valor;
            }
            $resultado[self::FILTROS[$claveInterna] ?? $clave] = $valor;
        }

        return $resultado;
    }

    public static function consultaInterna(array $consulta): array
    {
        $resultado = [];
        foreach ($consulta as $clave => $valor) {
            $clave = self::interno((string) $clave, self::FILTROS);
            $resultado[$clave] = is_string($valor) ? self::interno($valor, self::VALORES[$clave] ?? []) : $valor;
        }

        return $resultado;
    }

    public static function urlPublica(string $url): string
    {
        [$sinFragmento, $fragmento] = array_pad(explode('#', $url, 2), 2, null);
        [$base, $consulta] = array_pad(explode('?', $sinFragmento, 2), 2, null);
        if ($consulta === null) {
            return $url;
        }
        parse_str($consulta, $parametros);
        $publicos = self::consultaPublica($parametros);
        if ($publicos === $parametros) {
            return $url;
        }

        return $base.'?'.http_build_query($publicos, '', '&', PHP_QUERY_RFC3986).($fragmento === null ? '' : '#'.$fragmento);
    }

    public static function mapaClase(string $nombreRuta): array
    {
        return match (true) {
            str_starts_with($nombreRuta, 'operaciones-trabajadores.') => self::OPERACIONES,
            str_starts_with($nombreRuta, 'reportes-control.') => self::REPORTES,
            str_starts_with($nombreRuta, 'corrections.') => self::CORRECCIONES,
            default => [],
        };
    }

    public static function interno(string $valor, array $mapa): string
    {
        return array_search($valor, $mapa, true) ?: $valor;
    }

    public static function camino(string $camino): string
    {
        $partes = explode('/', trim($camino, '/'));
        if (count($partes) === 1) {
            return '/'.(['login' => 'ingresar', 'logout' => 'cerrar-sesion', 'up' => 'estado-sistema'][$partes[0]] ?? $partes[0]);
        }
        $mapa = match ($partes[0]) {
            'operaciones' => self::OPERACIONES,
            'reportes' => self::REPORTES,
            'correcciones' => self::CORRECCIONES,
            default => [],
        };
        $partes[1] = $mapa[$partes[1]] ?? $partes[1];
        $ultimo = array_key_last($partes);
        $recursos = ['usuarios', 'roles', 'trabajadores', 'productos', 'categorias', 'atributos-tecnicos', 'unidades-de-medida', 'unidades-individualizadas', 'ubicaciones', 'remitos', 'operaciones', 'correcciones'];
        if (in_array($partes[0], $recursos, true)) {
            $partes[$ultimo] = ['create' => 'crear', 'edit' => 'editar'][$partes[$ultimo]] ?? $partes[$ultimo];
        }
        if ($partes[0] === 'correcciones' && count($partes) === 4) {
            $partes[3] = self::ACCIONES[$partes[3]] ?? $partes[3];
        }
        if ($partes[0] === 'devoluciones' && ($partes[1] ?? '') === 'regularizaciones' && count($partes) === 4) {
            $partes[3] = self::ACCIONES[$partes[3]] ?? $partes[3];
        }

        return '/'.implode('/', $partes);
    }
}
