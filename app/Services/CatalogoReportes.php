<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class CatalogoReportes
{
    public const GROUPS = [
        'inventory' => ['label' => 'Inventario y kardex', 'kinds' => ['stock', 'movements', 'availability']],
        'remittances' => ['label' => 'Remitos', 'kinds' => ['remittances']],
        'deliveries' => ['label' => 'Entregas y EPP', 'kinds' => ['deliveries']],
        'tools' => ['label' => 'Herramientas y responsables', 'kinds' => ['responsibilities', 'loans', 'returns']],
        'control' => ['label' => 'Control y auditoría', 'kinds' => ['corrections', 'audit']],
    ];

    public function navigation(): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => $group) {
            $visible = array_values(array_filter($group['kinds'], fn ($clase) => Gate::allows(app(ReportesControl::class)->permiso($clase))));
            if ($visible) {
                $groups[$key] = $group + ['visible' => $visible, 'first' => $visible[0]];
            }
        }

        return $groups;
    }

    public function group(string $clase): array
    {
        foreach (self::GROUPS as $group) {
            if (in_array($clase, $group['kinds'], true)) {
                return $group;
            }
        }
        abort(404);
    }

    public function sections(string $clase): array
    {
        $producto = ['producto', 'nombre', 'categoria', 'identificadores'];
        $ubicacion = ['ubicacion', 'ubicacion_nombre'];
        $trabajador = ['trabajador', 'trabajador_codigo', 'cargo', 'area'];
        $documento = ['documento', 'fecha', 'linea', 'estado', 'compensado_por'];
        $people = ['creador', 'confirmador', 'confirmado'];

        return match ($clase) {
            'stock' => ['Artículo' => ['producto', 'nombre', 'categoria'], 'Ubicación' => $ubicacion, 'Existencia física' => ['cantidad', 'unidad'], 'Catálogo actual' => ['producto_activo']],
            'movements' => ['Registro' => ['movimiento', 'registrado'], 'Documento de origen' => ['documento', 'origen', 'linea', 'trabajador', 'movimiento_original'], 'Artículo' => $producto, 'Ubicación' => $ubicacion, 'Movimiento' => ['sentido', 'cantidad', 'unidad'], 'Saldo posterior' => ['saldo'], 'Operador' => ['operador']],
            'availability' => ['Herramienta' => ['producto', 'nombre', 'categoria'], 'Identidad' => ['identificador', 'serie'], 'Ubicación física' => $ubicacion, 'Situación actual' => ['situacion', 'presencia_fisica', 'identidad_activa', 'producto_activo'], 'Custodia' => ['prestamo', 'trabajador', 'vencimiento']],
            'responsibilities' => ['Trabajador responsable' => $trabajador, 'Herramienta pendiente' => ['producto', 'nombre', 'categoria'], 'Identidad' => ['identificador', 'serie'], 'Préstamo' => ['documento', 'fecha', 'vencimiento', 'situacion'], 'Retiro original' => $ubicacion, 'Estado actual' => ['trabajador_activo']],
            'audit' => ['Evento' => ['evento', 'registrado', 'accion'], 'Responsable' => ['operador'], 'Referencias' => ['correccion', 'respaldo', 'ensayo'], 'Detalle' => ['detalle']],
            'corrections' => ['Documento' => array_merge($documento, ['tipo']), 'Artículo' => $producto, 'Ubicación' => $ubicacion, 'Corrección' => ['sentido', 'cantidad', 'unidad', 'saldo_observado', 'conteo', 'movimiento_original'], 'Autorización más reciente' => ['autorizador', 'autorizado', 'autorizacion_estado'], 'Responsables' => ['creador', 'ejecutor', 'ejecutado'], 'Justificación' => ['motivo']],
            'remittances' => ['Documento' => array_merge($documento, ['tipo']), 'Contraparte' => ['contraparte', 'contraparte_codigo', 'referencia'], 'Artículo' => $producto, 'Ubicación' => $ubicacion, 'Cantidad documentada' => ['cantidad', 'unidad'], 'Responsables' => $people, 'Observaciones' => ['notas']],
            default => ['Documento' => $documento, 'Trabajador' => $trabajador, 'Artículo' => $producto, 'Ubicación' => $ubicacion, 'Cantidad documentada' => ['cantidad', 'unidad'], ($clase === 'deliveries' ? 'Observaciones' : 'Seguimiento') => $clase === 'loans' ? ['situacion', 'vencimiento'] : ($clase === 'returns' ? ['prestamo', 'item_prestamo'] : ['notas']), 'Responsables' => $clase === 'deliveries' ? $people : array_merge($people, ['notas'])],
        };
    }

    public function columns(string $clase): array
    {
        $labels = [
            'producto' => 'Código de artículo', 'nombre' => 'Descripción del artículo', 'categoria' => 'Categoría', 'identificadores' => 'Identificadores',
            'ubicacion' => 'Código de ubicación', 'ubicacion_nombre' => 'Nombre de ubicación', 'cantidad' => 'Cantidad', 'unidad' => 'Unidad', 'producto_activo' => 'Producto activo',
            'documento' => 'Documento', 'fecha' => 'Fecha documental', 'linea' => 'Línea / ítem', 'estado' => 'Estado documental', 'compensado_por' => 'Compensado por',
            'trabajador' => 'Trabajador', 'trabajador_codigo' => 'Código de trabajador', 'cargo' => 'Cargo documental', 'area' => 'Área documental',
            'tipo' => 'Tipo', 'contraparte' => 'Contraparte', 'contraparte_codigo' => 'Código de contraparte', 'referencia' => 'Referencia externa',
            'creador' => 'Preparado por', 'confirmador' => 'Confirmado por', 'confirmado' => 'Confirmación (Bolivia)', 'notas' => 'Observaciones',
            'movimiento' => 'Movimiento', 'registrado' => 'Registro (Bolivia)', 'origen' => 'Origen', 'sentido' => 'Sentido', 'saldo' => 'Saldo físico posterior', 'operador' => 'Operador',
            'identificador' => 'Identificador', 'serie' => 'Serie', 'situacion' => 'Situación actual', 'presencia_fisica' => 'Presencia física', 'identidad_activa' => 'Identidad activa', 'prestamo' => 'Préstamo relacionado', 'vencimiento' => 'Vencimiento', 'trabajador_activo' => 'Trabajador activo', 'item_prestamo' => 'Ítem original del préstamo',
            'saldo_observado' => 'Saldo observado', 'conteo' => 'Cantidad contada', 'ejecutor' => 'Ejecutado por', 'ejecutado' => 'Ejecución (Bolivia)', 'motivo' => 'Motivo',
            'evento' => 'Evento', 'accion' => 'Acción', 'correccion' => 'Corrección', 'respaldo' => 'Respaldo', 'ensayo' => 'Ensayo', 'detalle' => 'Detalle del evento',
            'movimiento_original' => 'Movimiento compensado', 'autorizador' => 'Autorizado por', 'autorizado' => 'Autorización (Bolivia)', 'autorizacion_estado' => 'Estado de autorización',
        ];
        $fields = array_unique(array_merge(...array_values($this->sections($clase))));

        return array_combine(array_values($fields), array_map(fn ($field) => $labels[$field], array_values($fields)));
    }

    public function filters(string $clase): array
    {
        return match ($clase) {
            'stock' => ['to', 'q', 'producto_id', 'categoria_id', 'ubicacion_id'],
            'availability' => ['q', 'producto_id', 'categoria_id', 'ubicacion_id'],
            'audit' => ['from', 'to', 'q'],
            'responsibilities' => ['from', 'to', 'q', 'producto_id', 'categoria_id', 'trabajador_id', 'custody'],
            'loans' => ['from', 'to', 'q', 'producto_id', 'categoria_id', 'ubicacion_id', 'trabajador_id', 'estado', 'custody'],
            'remittances', 'corrections' => ['from', 'to', 'q', 'producto_id', 'categoria_id', 'ubicacion_id', 'estado', 'tipo'],
            default => ['from', 'to', 'q', 'producto_id', 'categoria_id', 'ubicacion_id', 'trabajador_id', 'estado'],
        };
    }

    public function cell(string $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (in_array($column, ['registrado', 'confirmado', 'ejecutado', 'autorizado'], true)) {
            return CarbonImmutable::parse($value, 'UTC')->timezone('America/La_Paz')->format('d/m/Y H:i:s');
        }
        if (in_array($column, ['fecha', 'vencimiento'], true)) {
            return CarbonImmutable::parse($value)->format('d/m/Y');
        }
        if (in_array($column, ['presencia_fisica', 'identidad_activa', 'producto_activo', 'trabajador_activo'], true)) {
            return $value ? 'Sí' : 'No';
        }
        if (in_array($column, ['estado', 'autorizacion_estado'], true)) {
            return __('control.states.'.$value, [], 'es');
        }
        if ($column === 'tipo') {
            return __('control.kinds.'.$value, [], 'es');
        }
        if ($column === 'sentido') {
            return $value === 'in' ? 'Ingreso' : 'Egreso';
        }
        if ($column === 'accion') {
            return [
                'correccion.saved' => 'Corrección preparada', 'correccion.request' => 'Autorización de corrección solicitada',
                'correccion.approve' => 'Corrección autorizada', 'correccion.reject' => 'Corrección rechazada', 'correccion.revoke' => 'Autorización de corrección revocada', 'correccion.cancel' => 'Corrección cancelada', 'correccion.executed' => 'Corrección ejecutada',
                'backup.requested' => 'Respaldo solicitado', 'backup.started' => 'Generación de respaldo iniciada', 'backup.generated' => 'Respaldo generado', 'backup.failed' => 'Generación de respaldo fallida', 'backup.integrity_verified' => 'Integridad de respaldo verificada', 'backup.download_authorized' => 'Descarga de respaldo autorizada',
                'rehearsal.requested' => 'Ensayo de restauración solicitado', 'rehearsal.approved' => 'Ensayo autorizado', 'rehearsal.started' => 'Ensayo iniciado', 'rehearsal.verified' => 'Ensayo verificado', 'rehearsal.revoked' => 'Ensayo revocado',
            ][$value] ?? (string) $value;
        }
        if (in_array($column, ['cantidad', 'saldo', 'saldo_observado', 'conteo'], true)) {
            // Preserve DECIMAL precision; never round through a PHP float.
            return str_contains((string) $value, '.') ? rtrim(rtrim((string) $value, '0'), '.') : (string) $value;
        }

        return (string) $value;
    }

    public function documentUrl(string $codigo, array $permisos, ?string $correctionKind = null): ?string
    {
        if (! preg_match('/^RG-(IN|EG|AP|EN|PR|DV|CO)-([0-9]+)$/D', $codigo, $parts)) {
            return null;
        }
        $id = ltrim($parts[2], '0') ?: '0';
        if (in_array($parts[1], ['IN', 'EG', 'AP'], true)) {
            return isset($permisos[$parts[1] === 'AP' ? 'initial_stock.view' : 'remittances.view']) ? route('documentos-inventario.show', $id) : null;
        }
        if ($parts[1] === 'CO') {
            if (! in_array($correctionKind, ['adjustment', 'compensation'], true)) {
                return null;
            }

            return isset($permisos[$correctionKind === 'adjustment' ? 'adjustments.view' : 'compensations.view']) ? route('corrections.show', [$correctionKind, $id]) : null;
        }
        $clase = ['EN' => 'deliveries', 'PR' => 'loans', 'DV' => 'returns'][$parts[1]];

        return isset($permisos[$clase.'.view']) ? route('operaciones-trabajadores.show', [$clase, $id]) : null;
    }
}
