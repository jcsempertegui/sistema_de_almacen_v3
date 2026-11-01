<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class DetailedReportQuery
{
    public function query(string $kind, array $filters): Builder
    {
        $base = match ($kind) {
            'stock' => $this->stock($filters['to'] ?? null),
            'movements' => $this->movements(),
            'availability' => $this->availability(),
            'responsibilities' => $this->responsibilities(),
            'audit' => DB::table('critical_operation_events as a')->select('a.id as evento', 'a.created_at as registrado', 'a.created_at as _date', 'a.actor_name as operador', 'a.action as accion', 'a.correction_id as correccion', 'a.backup_run_id as respaldo', 'a.rehearsal_id as ensayo', 'a.payload as detalle'),
            default => $this->document($kind),
        };
        // The kardex window is calculated before date filters, preserving its opening balance.
        $query = DB::query()->fromSub($base, 'r')->select('r.*');
        foreach (['product_id', 'location_id', 'category_id', 'worker_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where('r._'.$filter, $filters[$filter]);
            }
        }
        if (! in_array($kind, ['stock', 'availability'], true)) {
            $timestamp = in_array($kind, ['movements', 'audit'], true);
            if (! empty($filters['from'])) {
                $query->where('r._date', '>=', $timestamp ? CarbonImmutable::parse($filters['from'], 'America/La_Paz')->utc() : $filters['from']);
            }
            if (! empty($filters['to'])) {
                $query->where('r._date', '<', $timestamp ? CarbonImmutable::parse($filters['to'], 'America/La_Paz')->addDay()->utc() : CarbonImmutable::parse($filters['to'])->addDay()->toDateString());
            }
        }
        if (! empty($filters['status'])) {
            $query->where('r.estado', $filters['status']);
        }
        if (! empty($filters['type'])) {
            $query->where('r.tipo', $filters['type']);
        }
        if (! empty($filters['custody'])) {
            $query->whereIn('r.situacion', match ($filters['custody']) {
                'pending' => ['Pendiente', 'Vencido'], 'overdue' => ['Vencido'], default => ['Devuelto'],
            });
        }
        if (! empty($filters['q'])) {
            $fields = array_intersect(['producto', 'nombre', 'documento', 'trabajador', 'trabajador_codigo', 'identificador', 'identificadores', 'serie', 'contraparte', 'referencia', 'notas', 'motivo', 'operador', 'accion', 'detalle'], array_keys(app(ReportCatalog::class)->columns($kind)));
            $pattern = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($query) use ($fields, $pattern) {
                foreach ($fields as $field) {
                    $query->orWhere('r.'.$field, 'like', $pattern);
                }
            });
        }

        return match ($kind) {
            'stock' => $query->orderBy('producto')->orderBy('ubicacion'),
            'movements' => $query->orderBy('registrado')->orderBy('movimiento'),
            'availability' => $query->orderBy('identificador'),
            'audit' => $query->orderByDesc('evento'),
            'responsibilities' => $query->orderByRaw('vencimiento IS NULL')->orderBy('vencimiento')->orderBy('documento')->orderBy('identificador'),
            default => $query->orderByDesc('fecha')->orderByDesc('_document_id')->orderBy('linea'),
        };
    }

    private function code(string $id, string $prefix): string
    {
        return "CONCAT('RG-', {$prefix}, '-', LPAD({$id}, GREATEST(8,CHAR_LENGTH({$id})), '0'))";
    }

    private function products(Builder $q, string $product = 'x.product_id', string $location = 'x.location_id'): Builder
    {
        return $q->join('products as p', 'p.id', '=', $product)->join('categories as cat', 'cat.id', '=', 'p.category_id')
            ->leftJoin('locations as l', 'l.id', '=', $location)
            ->addSelect('p.id as _product_id', 'p.category_id as _category_id', 'l.id as _location_id', 'p.internal_code as producto', 'p.name as nombre', 'cat.name as categoria', 'l.code as ubicacion', 'l.name as ubicacion_nombre');
    }

    private function identifiers(Builder $q, string $table, string $key, string $line): void
    {
        $identifiers = DB::table($table.' as link')->join('individual_units as u', 'u.id', '=', 'link.individual_unit_id')
            ->select('link.'.$key)->selectRaw("GROUP_CONCAT(u.identifier ORDER BY u.identifier SEPARATOR ', ') AS identifiers")->groupBy('link.'.$key);
        $q->leftJoinSub($identifiers, 'identities', 'identities.'.$key, '=', $line)->addSelect('identities.identifiers as identificadores');
    }

    private function worker(Builder $q, string $document = 'd'): void
    {
        $q->join('workers as w', 'w.id', '=', $document.'.worker_id')->addSelect($document.'.worker_id as _worker_id');
        foreach (['codigo' => 'trabajador_codigo', 'cargo' => 'cargo', 'area' => 'area'] as $field => $alias) {
            $q->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$document}.worker_snapshot,'$.{$field}')),w.{$field}) AS {$alias}");
        }
        $q->selectRaw("COALESCE(CONCAT(JSON_UNQUOTE(JSON_EXTRACT({$document}.worker_snapshot,'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT({$document}.worker_snapshot,'$.apellidos'))), CONCAT(w.nombres,' ',w.apellidos)) AS trabajador");
    }

    private function stock(?string $cutoff): Builder
    {
        $ledger = DB::table('inventory_movements')->select('product_id', 'location_id')
            ->selectRaw("CAST(SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) AS DECIMAL(24,6)) AS cantidad")->groupBy('product_id', 'location_id');
        if ($cutoff) {
            $ledger->where('posted_at', '<', CarbonImmutable::parse($cutoff, 'America/La_Paz')->addDay()->utc());
        }
        $q = DB::query()->fromSub($ledger, 'x')->select('x.cantidad');
        $this->products($q);

        return $q->join('measurement_units as mu', 'mu.id', '=', 'p.measurement_unit_id')->addSelect('mu.symbol as unidad', 'p.is_active as producto_activo');
    }

    private function document(string $kind): Builder
    {
        [$table, $lines, $fk, $prefix] = match ($kind) {
            'remittances' => ['inventory_documents', 'inventory_document_lines', 'document_id', "CASE d.kind WHEN 'receipt' THEN 'IN' WHEN 'issue' THEN 'EG' ELSE 'AP' END"],
            'deliveries' => ['material_deliveries', 'material_delivery_lines', 'delivery_id', "'EN'"],
            'loans' => ['tool_loans', 'tool_loan_items', 'loan_id', "'PR'"],
            'returns' => ['tool_returns', 'tool_return_items', 'return_id', "'DV'"],
            'corrections' => ['inventory_corrections', 'inventory_correction_lines', 'correction_id', "'CO'"],
        };
        $q = DB::table($table.' as d')->join($lines.' as x', 'x.'.$fk, '=', 'd.id')
            ->leftJoin('users as creator', 'creator.id', '=', 'd.created_by')
            ->select('d.id as _document_id', 'd.document_date as fecha', 'd.document_date as _date', 'd.status as estado', 'creator.name as creador')
            ->selectRaw($this->code('d.id', $prefix).' AS documento');
        $this->products($q);
        $sourceColumn = ['remittances' => 'inventory_document_id', 'deliveries' => 'delivery_id', 'loans' => 'loan_id', 'returns' => 'return_id', 'corrections' => 'target_correction_id'][$kind];
        $q->leftJoin('document_compensations as dc', 'dc.'.$sourceColumn, '=', 'd.id')->selectRaw('CASE WHEN dc.correction_id IS NOT NULL THEN '.$this->code('dc.correction_id', "'CO'").' END AS compensado_por');
        if ($kind === 'corrections') {
            $latest = DB::table('correction_authorizations')->select('correction_id')->selectRaw('MAX(id) AS authorization_id')->groupBy('correction_id');
            $q->leftJoinSub($latest, 'latest', 'latest.correction_id', '=', 'd.id')->leftJoin('correction_authorizations as authorization', 'authorization.id', '=', 'latest.authorization_id')
                ->leftJoin('users as approver', 'approver.id', '=', 'authorization.approved_by')
                ->leftJoin('users as executor', 'executor.id', '=', 'd.executed_by')->addSelect('x.id as linea', 'd.kind as tipo', 'd.reason as motivo', 'x.direction as sentido', 'x.observed_quantity as saldo_observado', 'x.counted_quantity as conteo', 'x.quantity as cantidad', 'x.unit_symbol as unidad', 'executor.name as ejecutor', 'd.executed_at as ejecutado', 'x.original_movement_id as movimiento_original', 'approver.name as autorizador', 'authorization.approved_at as autorizado', 'authorization.status as autorizacion_estado');
            $this->identifiers($q, 'inventory_correction_line_units', 'correction_line_id', 'x.id');

            return $q;
        }
        $q->leftJoin('users as confirmer', 'confirmer.id', '=', 'd.confirmed_by')->addSelect('confirmer.name as confirmador', 'd.confirmed_at as confirmado', 'd.notes as notas');
        if ($kind === 'remittances') {
            $q->addSelect('x.line_number as linea', 'd.kind as tipo', 'd.counterparty_name as contraparte', 'd.counterparty_id as contraparte_codigo', 'd.external_reference as referencia', 'x.quantity as cantidad', 'x.unit_symbol as unidad');
            $this->identifiers($q, 'inventory_document_line_units', 'document_line_id', 'x.id');
        } else {
            $this->worker($q);
            if ($kind === 'deliveries') {
                $q->addSelect('x.line_number as linea', 'x.quantity as cantidad', 'x.unit_symbol as unidad');
                $this->identifiers($q, 'material_delivery_line_units', 'delivery_line_id', 'x.id');
            } else {
                $q->join('individual_units as u', 'u.id', '=', 'x.individual_unit_id')->addSelect('x.id as linea', 'u.identifier as identificadores')->selectRaw('1 AS cantidad');
                if ($kind === 'loans') {
                    $q->leftJoin('individual_unit_custodies as custody', 'custody.individual_unit_id', '=', 'x.individual_unit_id')->addSelect('x.unit_symbol as unidad', 'd.due_date as vencimiento');
                    $q->selectRaw("CASE WHEN d.status='draft' THEN 'Borrador' WHEN d.status='cancelled' THEN 'Cancelado' WHEN dc.id IS NOT NULL THEN 'Compensado' WHEN custody.current_loan_item_id=x.id THEN CASE WHEN d.due_date < ? THEN 'Vencido' ELSE 'Pendiente' END ELSE 'Devuelto' END AS situacion", [CarbonImmutable::now('America/La_Paz')->toDateString()]);
                } else {
                    $q->join('tool_loan_items as original', 'original.id', '=', 'x.loan_item_id')->addSelect('original.unit_symbol as unidad', 'x.loan_item_id as item_prestamo')->selectRaw($this->code('d.loan_id', "'PR'").' AS prestamo');
                }
            }
        }

        return $q;
    }

    private function responsibilities(): Builder
    {
        $q = DB::table('individual_unit_custodies as custody')->join('tool_loan_items as x', 'x.id', '=', 'custody.current_loan_item_id')->join('tool_loans as d', 'd.id', '=', 'x.loan_id')->join('individual_units as u', 'u.id', '=', 'x.individual_unit_id')
            ->where('d.status', 'confirmed')->whereNotNull('custody.current_cycle_id')
            ->select('u.identifier as identificador', 'u.serial_number as serie', 'd.document_date as fecha', 'd.document_date as _date', 'd.due_date as vencimiento')->selectRaw($this->code('d.id', "'PR'").' AS documento');
        $this->products($q);
        $this->worker($q);
        $q->addSelect('w.is_active as trabajador_activo')->selectRaw("CASE WHEN d.due_date < ? THEN 'Vencido' ELSE 'Pendiente' END AS situacion", [CarbonImmutable::now('America/La_Paz')->toDateString()]);

        return $q;
    }

    private function availability(): Builder
    {
        $q = DB::table('individual_unit_positions as x')->join('individual_units as u', 'u.id', '=', 'x.individual_unit_id')->leftJoin('individual_unit_custodies as custody', 'custody.individual_unit_id', '=', 'u.id')->leftJoin('tool_loan_items as item', 'item.id', '=', 'custody.current_loan_item_id')->leftJoin('tool_loans as loan', 'loan.id', '=', 'item.loan_id')->leftJoin('workers as w', 'w.id', '=', 'loan.worker_id')
            ->select('u.identifier as identificador', 'u.serial_number as serie', 'x.is_present as presencia_fisica', 'u.is_active as identidad_activa', 'loan.due_date as vencimiento')->selectRaw('CASE WHEN loan.id IS NOT NULL THEN '.$this->code('loan.id', "'PR'").' END AS prestamo')
            ->selectRaw("COALESCE(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(loan.worker_snapshot,'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT(loan.worker_snapshot,'$.apellidos'))),CONCAT(w.nombres,' ',w.apellidos)) AS trabajador")
            ->selectRaw("CASE WHEN custody.current_loan_item_id IS NOT NULL THEN 'Prestado' WHEN x.is_present=1 THEN 'En almacén' ELSE 'Fuera del almacén' END AS situacion");

        return $this->products($q)->addSelect('p.is_active as producto_activo');
    }

    private function movements(): Builder
    {
        $q = DB::table('inventory_movements as x')
            ->leftJoin('inventory_document_lines as il', 'il.id', '=', 'x.document_line_id')->leftJoin('inventory_documents as doc', 'doc.id', '=', 'il.document_id')
            ->leftJoin('material_delivery_lines as dl', 'dl.id', '=', 'x.delivery_line_id')->leftJoin('material_deliveries as delivery', 'delivery.id', '=', 'dl.delivery_id')
            ->leftJoin('tool_loan_items as li', 'li.id', '=', 'x.loan_item_id')->leftJoin('tool_loans as loan', 'loan.id', '=', 'li.loan_id')
            ->leftJoin('tool_return_items as ri', 'ri.id', '=', 'x.return_item_id')->leftJoin('tool_returns as returned', 'returned.id', '=', 'ri.return_id')->leftJoin('tool_loan_items as rli', 'rli.id', '=', 'ri.loan_item_id')
            ->leftJoin('inventory_correction_lines as cl', 'cl.id', '=', 'x.correction_line_id')->leftJoin('inventory_corrections as correction', 'correction.id', '=', 'cl.correction_id')
            ->join('users as operator', 'operator.id', '=', 'x.created_by')
            ->select('x.id as movimiento', 'x.posted_at as registrado', 'x.posted_at as _date', 'x.direction as sentido', 'x.quantity as cantidad', 'operator.name as operador', 'correction.kind as _correction_kind', 'x.compensated_movement_id as movimiento_original')
            ->selectRaw('COALESCE(il.line_number,dl.line_number,li.id,ri.id,cl.id) AS linea, COALESCE(il.unit_symbol,dl.unit_symbol,li.unit_symbol,rli.unit_symbol,cl.unit_symbol) AS unidad')
            ->selectRaw("SUM(CASE WHEN x.direction='in' THEN x.quantity ELSE -x.quantity END) OVER (PARTITION BY x.product_id,x.location_id ORDER BY x.posted_at,x.id ROWS UNBOUNDED PRECEDING) AS saldo")
            ->selectRaw('COALESCE('.$this->code('doc.id', "CASE doc.kind WHEN 'receipt' THEN 'IN' WHEN 'issue' THEN 'EG' ELSE 'AP' END").','.$this->code('delivery.id', "'EN'").','.$this->code('loan.id', "'PR'").','.$this->code('returned.id', "'DV'").','.$this->code('correction.id', "'CO'").') AS documento')
            ->selectRaw("CASE WHEN doc.id IS NOT NULL THEN 'Remito / apertura' WHEN delivery.id IS NOT NULL THEN 'Entrega' WHEN loan.id IS NOT NULL THEN 'Préstamo' WHEN returned.id IS NOT NULL THEN 'Devolución' ELSE 'Corrección' END AS origen")
            ->selectRaw("CONCAT(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(delivery.worker_snapshot,loan.worker_snapshot,returned.worker_snapshot),'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT(COALESCE(delivery.worker_snapshot,loan.worker_snapshot,returned.worker_snapshot),'$.apellidos'))) AS trabajador");
        $this->products($q);
        // Aggregate identities once per source line so a quantity is never multiplied by its identities.
        $parts = [];
        foreach ([['inventory_document_line_units', 'document_line_id', 'x.document_line_id'], ['material_delivery_line_units', 'delivery_line_id', 'x.delivery_line_id'], ['inventory_correction_line_units', 'correction_line_id', 'x.correction_line_id']] as $i => [$table, $key, $column]) {
            $sub = DB::table($table.' as link')->join('individual_units as unit', 'unit.id', '=', 'link.individual_unit_id')->select('link.'.$key)->selectRaw("GROUP_CONCAT(unit.identifier ORDER BY unit.identifier SEPARATOR ', ') AS ids")->groupBy('link.'.$key);
            $q->leftJoinSub($sub, 'ids'.$i, 'ids'.$i.'.'.$key, '=', $column);
            $parts[] = 'ids'.$i.'.ids';
        }
        $q->leftJoin('individual_units as loan_unit', 'loan_unit.id', '=', 'li.individual_unit_id')->leftJoin('individual_units as return_unit', 'return_unit.id', '=', 'ri.individual_unit_id');

        return $q->selectRaw('COALESCE('.implode(',', $parts).',loan_unit.identifier,return_unit.identifier) AS identificadores');
    }
}
