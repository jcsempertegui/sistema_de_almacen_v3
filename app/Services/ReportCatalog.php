<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class ReportCatalog
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
            $visible = array_values(array_filter($group['kinds'], fn ($kind) => Gate::allows(app(ControlReports::class)->permission($kind))));
            if ($visible) {
                $groups[$key] = $group + ['visible' => $visible, 'first' => $visible[0]];
            }
        }

        return $groups;
    }

    public function group(string $kind): array
    {
        foreach (self::GROUPS as $group) {
            if (in_array($kind, $group['kinds'], true)) {
                return $group;
            }
        }
        abort(404);
    }

    public function sections(string $kind): array
    {
        $product = ['producto', 'nombre', 'categoria', 'identificadores'];
        $location = ['ubicacion', 'ubicacion_nombre'];
        $worker = ['trabajador', 'trabajador_codigo', 'cargo', 'area'];
        $document = ['documento', 'fecha', 'linea', 'estado', 'compensado_por'];
        $people = ['creador', 'confirmador', 'confirmado'];

        return match ($kind) {
            'stock' => ['Artículo' => ['producto', 'nombre', 'categoria'], 'Ubicación' => $location, 'Existencia física' => ['cantidad', 'unidad'], 'Catálogo actual' => ['producto_activo']],
            'movements' => ['Registro' => ['movimiento', 'registrado'], 'Documento de origen' => ['documento', 'origen', 'linea', 'trabajador', 'movimiento_original'], 'Artículo' => $product, 'Ubicación' => $location, 'Movimiento' => ['sentido', 'cantidad', 'unidad'], 'Saldo posterior' => ['saldo'], 'Operador' => ['operador']],
            'availability' => ['Herramienta' => ['producto', 'nombre', 'categoria'], 'Identidad' => ['identificador', 'serie'], 'Ubicación física' => $location, 'Situación actual' => ['situacion', 'presencia_fisica', 'identidad_activa', 'producto_activo'], 'Custodia' => ['prestamo', 'trabajador', 'vencimiento']],
            'responsibilities' => ['Trabajador responsable' => $worker, 'Herramienta pendiente' => ['producto', 'nombre', 'categoria'], 'Identidad' => ['identificador', 'serie'], 'Préstamo' => ['documento', 'fecha', 'vencimiento', 'situacion'], 'Retiro original' => $location, 'Estado actual' => ['trabajador_activo']],
            'audit' => ['Evento' => ['evento', 'registrado', 'accion'], 'Responsable' => ['operador'], 'Referencias' => ['correccion', 'respaldo', 'ensayo'], 'Detalle' => ['detalle']],
            'corrections' => ['Documento' => array_merge($document, ['tipo']), 'Artículo' => $product, 'Ubicación' => $location, 'Corrección' => ['sentido', 'cantidad', 'unidad', 'saldo_observado', 'conteo', 'movimiento_original'], 'Autorización más reciente' => ['autorizador', 'autorizado', 'autorizacion_estado'], 'Responsables' => ['creador', 'ejecutor', 'ejecutado'], 'Justificación' => ['motivo']],
            'remittances' => ['Documento' => array_merge($document, ['tipo']), 'Contraparte' => ['contraparte', 'contraparte_codigo', 'referencia'], 'Artículo' => $product, 'Ubicación' => $location, 'Cantidad documentada' => ['cantidad', 'unidad'], 'Responsables' => $people, 'Observaciones' => ['notas']],
            default => ['Documento' => $document, 'Trabajador' => $worker, 'Artículo' => $product, 'Ubicación' => $location, 'Cantidad documentada' => ['cantidad', 'unidad'], ($kind === 'deliveries' ? 'Observaciones' : 'Seguimiento') => $kind === 'loans' ? ['situacion', 'vencimiento'] : ($kind === 'returns' ? ['prestamo', 'item_prestamo'] : ['notas']), 'Responsables' => $kind === 'deliveries' ? $people : array_merge($people, ['notas'])],
        };
    }

    public function columns(string $kind): array
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
        $fields = array_unique(array_merge(...array_values($this->sections($kind))));

        return array_combine(array_values($fields), array_map(fn ($field) => $labels[$field], array_values($fields)));
    }

    public function filters(string $kind): array
    {
        return match ($kind) {
            'stock' => ['to', 'q', 'product_id', 'category_id', 'location_id'],
            'availability' => ['q', 'product_id', 'category_id', 'location_id'],
            'audit' => ['from', 'to', 'q'],
            'responsibilities' => ['from', 'to', 'q', 'product_id', 'category_id', 'worker_id', 'custody'],
            'loans' => ['from', 'to', 'q', 'product_id', 'category_id', 'location_id', 'worker_id', 'status', 'custody'],
            'remittances', 'corrections' => ['from', 'to', 'q', 'product_id', 'category_id', 'location_id', 'status', 'type'],
            default => ['from', 'to', 'q', 'product_id', 'category_id', 'location_id', 'worker_id', 'status'],
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
                'correction.saved' => 'Corrección preparada', 'correction.request' => 'Autorización de corrección solicitada',
                'correction.approve' => 'Corrección autorizada', 'correction.reject' => 'Corrección rechazada', 'correction.revoke' => 'Autorización de corrección revocada', 'correction.cancel' => 'Corrección cancelada', 'correction.executed' => 'Corrección ejecutada',
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

    public function documentUrl(string $code, array $permissions, ?string $correctionKind = null): ?string
    {
        if (! preg_match('/^RG-(IN|EG|AP|EN|PR|DV|CO)-([0-9]+)$/D', $code, $parts)) {
            return null;
        }
        $id = ltrim($parts[2], '0') ?: '0';
        if (in_array($parts[1], ['IN', 'EG', 'AP'], true)) {
            return isset($permissions[$parts[1] === 'AP' ? 'initial_stock.view' : 'remittances.view']) ? route('inventory-documents.show', $id) : null;
        }
        if ($parts[1] === 'CO') {
            if (! in_array($correctionKind, ['adjustment', 'compensation'], true)) {
                return null;
            }

            return isset($permissions[$correctionKind === 'adjustment' ? 'adjustments.view' : 'compensations.view']) ? route('corrections.show', [$correctionKind, $id]) : null;
        }
        $kind = ['EN' => 'deliveries', 'PR' => 'loans', 'DV' => 'returns'][$parts[1]];

        return isset($permissions[$kind.'.view']) ? route('worker-operations.show', [$kind, $id]) : null;
    }
}
