<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ControlReports
{
    public const LABELS = ['stock' => 'Existencias', 'movements' => 'Movimientos', 'remittances' => 'Remitos y aperturas', 'deliveries' => 'Entregas', 'loans' => 'Préstamos', 'returns' => 'Devoluciones', 'responsibilities' => 'Responsabilidades pendientes', 'corrections' => 'Correcciones', 'availability' => 'Disponibilidad', 'audit' => 'Auditoría crítica'];

    public function permission(string $kind, string $action = 'view'): string
    {
        abort_unless(isset(self::LABELS[$kind]), 404);

        return $kind === 'audit' ? 'critical_audit.'.$action : 'reports.'.$kind.'.'.$action;
    }

    public function query(string $kind, array $filters)
    {
        $this->permission($kind);
        $q = null;
        $date = null;
        if ($kind === 'stock') {
            $ledger = DB::table('inventory_movements')->select('product_id', 'location_id')->selectRaw("CAST(SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) AS DECIMAL(24,6)) AS cantidad")->groupBy('product_id', 'location_id');
            if (! empty($filters['to'])) {
                $ledger->where('posted_at', '<', CarbonImmutable::parse($filters['to'], 'America/La_Paz')->addDay()->utc());
            }
            $q = DB::query()->fromSub($ledger, 's')->join('products as p', 'p.id', '=', 's.product_id')->join('locations as l', 'l.id', '=', 's.location_id')->join('measurement_units as mu', 'mu.id', '=', 'p.measurement_unit_id')->select('p.internal_code as producto', 'p.name as nombre', 'l.code as ubicacion', 's.cantidad', 'mu.symbol as unidad')->orderBy('p.internal_code')->orderBy('l.code');
        } elseif ($kind === 'availability') {
            $q = DB::table('individual_unit_positions as pos')->join('individual_units as u', 'u.id', '=', 'pos.individual_unit_id')->join('products as p', 'p.id', '=', 'pos.product_id')->leftJoin('locations as l', 'l.id', '=', 'pos.location_id')->leftJoin('individual_unit_custodies as c', 'c.individual_unit_id', '=', 'u.id')->select('u.identifier as identificador', 'p.internal_code as producto', 'l.code as ubicacion', 'pos.is_present as presencia_fisica', 'u.is_active as identidad_activa', 'p.is_active as producto_activo', 'c.current_loan_item_id as prestamo_item')->orderBy('u.identifier');
        } elseif ($kind === 'movements') {
            $q = DB::table('inventory_movements as m')->join('products as p', 'p.id', '=', 'm.product_id')->join('locations as l', 'l.id', '=', 'm.location_id')->leftJoin('inventory_document_lines as il', 'il.id', '=', 'm.document_line_id')->leftJoin('material_delivery_lines as dl', 'dl.id', '=', 'm.delivery_line_id')->leftJoin('tool_loan_items as li', 'li.id', '=', 'm.loan_item_id')->leftJoin('tool_return_items as ri', 'ri.id', '=', 'm.return_item_id')->leftJoin('tool_loan_items as rli', 'rli.id', '=', 'ri.loan_item_id')->leftJoin('inventory_correction_lines as cl', 'cl.id', '=', 'm.correction_line_id')->selectRaw('COALESCE(il.unit_symbol,dl.unit_symbol,li.unit_symbol,rli.unit_symbol,cl.unit_symbol) AS unidad')->addSelect('m.id as movimiento', 'm.posted_at as registrado_utc', 'p.internal_code as producto', 'l.code as ubicacion', 'm.direction as sentido', 'm.quantity as cantidad', 'm.document_line_id as remito_linea', 'm.delivery_line_id as entrega_linea', 'm.loan_item_id as prestamo_item', 'm.return_item_id as devolucion_item', 'm.correction_line_id as correccion_linea', 'm.compensated_movement_id as compensa_movimiento', 'm.created_by as operador')->orderBy('m.id');
            $date = 'm.posted_at';
        } elseif ($kind === 'responsibilities') {
            $q = DB::table('individual_unit_custodies as c')->join('tool_loan_items as li', 'li.id', '=', 'c.current_loan_item_id')->join('tool_loans as d', 'd.id', '=', 'li.loan_id')->join('individual_units as u', 'u.id', '=', 'li.individual_unit_id')->join('products as p', 'p.id', '=', 'li.product_id')->join('workers as w', 'w.id', '=', 'd.worker_id')->whereNotNull('c.current_cycle_id')->select('d.id as prestamo', 'd.worker_id as trabajador_id', 'u.identifier as identificador', 'p.internal_code as producto', 'd.document_date as fecha', 'd.due_date as vencimiento', 'w.is_active as trabajador_activo', 'c.current_cycle_id as ciclo')->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(d.worker_snapshot,'$.codigo')) AS trabajador_codigo, CONCAT(JSON_UNQUOTE(JSON_EXTRACT(d.worker_snapshot,'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT(d.worker_snapshot,'$.apellidos'))) AS trabajador")->orderBy('d.id')->orderBy('u.identifier');
            $date = 'd.document_date';
        } elseif ($kind === 'audit') {
            $q = DB::table('critical_operation_events as a')->select('a.id', 'a.created_at as fecha_utc', 'a.actor_name as operador', 'a.action as accion', 'a.correction_id as correccion', 'a.backup_run_id as respaldo', 'a.rehearsal_id as ensayo', 'a.payload as detalle')->orderBy('a.id');
            $date = 'a.created_at';
        } else {
            $table = ['remittances' => 'inventory_documents', 'deliveries' => 'material_deliveries', 'loans' => 'tool_loans', 'returns' => 'tool_returns', 'corrections' => 'inventory_corrections'][$kind];
            $q = DB::table($table.' as d')->select('d.id as documento', 'd.document_date as fecha', 'd.status as estado', 'd.created_by as creador');
            if ($kind === 'remittances') {
                $q->addSelect('d.kind as tipo', 'd.counterparty_id as contraparte_codigo', 'd.counterparty_name as contraparte', 'd.external_reference as referencia');
            } elseif ($kind === 'corrections') {
                $q->addSelect('d.kind as tipo', 'd.reason as motivo', 'd.executed_by as ejecutor', 'd.executed_at as ejecutado_utc');
            } else {
                $q->addSelect('d.worker_id as trabajador_id', 'd.confirmed_at as confirmado_utc', 'd.confirmed_by as confirmador')->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(d.worker_snapshot,'$.codigo')) AS trabajador_codigo, CONCAT(JSON_UNQUOTE(JSON_EXTRACT(d.worker_snapshot,'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT(d.worker_snapshot,'$.apellidos'))) AS trabajador");
                if ($kind === 'loans') {
                    $q->addSelect('d.due_date as vencimiento');
                }if ($kind === 'returns') {
                    $q->addSelect('d.loan_id as prestamo');
                }
            }
            $sourceColumn = ['remittances' => 'inventory_document_id', 'deliveries' => 'delivery_id', 'loans' => 'loan_id', 'returns' => 'return_id', 'corrections' => 'target_correction_id'][$kind];
            $q->leftJoin('document_compensations as dc', 'dc.'.$sourceColumn, '=', 'd.id')->addSelect('dc.correction_id as compensado_por')->orderBy('d.id');
            $date = 'd.document_date';
            if (! empty($filters['status'])) {
                $q->where('d.status', $filters['status']);
            }
        }
        if ($date) {
            if (! empty($filters['from'])) {
                $q->where($date, '>=', str_contains($date, 'at') ? CarbonImmutable::parse($filters['from'], 'America/La_Paz')->utc() : $filters['from']);
            }if (! empty($filters['to'])) {
                $q->where($date, '<', str_contains($date, 'at') ? CarbonImmutable::parse($filters['to'], 'America/La_Paz')->addDay()->utc() : CarbonImmutable::parse($filters['to'])->addDay()->toDateString());
            }
        }
        if (! empty($filters['product_id']) && in_array($kind, ['stock', 'availability', 'movements', 'responsibilities'])) {
            $q->where('p.id', $filters['product_id']);
        }
        if (! empty($filters['location_id']) && in_array($kind, ['stock', 'availability', 'movements'])) {
            $q->where('l.id', $filters['location_id']);
        }
        if (! empty($filters['worker_id']) && in_array($kind, ['deliveries', 'loans', 'returns', 'responsibilities'])) {
            $q->where('d.worker_id', $filters['worker_id']);
        }

        return $q;
    }

    public static function csvCell(mixed $value): string
    {
        $s = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-]|^[\t\r\n]/u', $s) ? "'".$s : $s;
    }
}
