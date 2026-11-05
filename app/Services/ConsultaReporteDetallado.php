<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class ConsultaReporteDetallado
{
    public function query(string $clase, array $filters): Builder
    {
        $base = match ($clase) {
            'stock' => $this->stock($filters['to'] ?? null),
            'movements' => $this->movements(),
            'availability' => $this->availability(),
            'responsibilities' => $this->responsibilities(),
            'audit' => DB::table('eventos_operacion_critica as a')->select('a.id as evento', 'a.fecha_creacion as registrado', 'a.fecha_creacion as _date', 'a.nombre_actor as operador', 'a.accion as accion', 'a.correccion_id as correccion', 'a.ejecucion_respaldo_id as respaldo', 'a.ensayo_id as ensayo', 'a.detalle as detalle'),
            default => $this->documento($clase),
        };
        // The kardex window is calculated before date filters, preserving its opening balance.
        $query = DB::query()->fromSub($base, 'r')->select('r.*');
        foreach (['producto_id', 'ubicacion_id', 'categoria_id', 'trabajador_id'] as $filter) {
            if (! empty($filters[$filter])) {
                $query->where('r._'.$filter, $filters[$filter]);
            }
        }
        if (! in_array($clase, ['stock', 'availability'], true)) {
            $timestamp = in_array($clase, ['movements', 'audit'], true);
            if (! empty($filters['from'])) {
                $query->where('r._date', '>=', $timestamp ? CarbonImmutable::parse($filters['from'], 'America/La_Paz')->utc() : $filters['from']);
            }
            if (! empty($filters['to'])) {
                $query->where('r._date', '<', $timestamp ? CarbonImmutable::parse($filters['to'], 'America/La_Paz')->addDay()->utc() : CarbonImmutable::parse($filters['to'])->addDay()->toDateString());
            }
        }
        if (! empty($filters['estado'])) {
            $query->where('r.estado', $filters['estado']);
        }
        if (! empty($filters['tipo'])) {
            $query->where('r.tipo', $filters['tipo']);
        }
        if (! empty($filters['custody'])) {
            $query->whereIn('r.situacion', match ($filters['custody']) {
                'pending' => ['Pendiente', 'Vencido'], 'overdue' => ['Vencido'], default => ['Devuelto'],
            });
        }
        if (! empty($filters['q'])) {
            $fields = array_intersect(['producto', 'nombre', 'documento', 'trabajador', 'trabajador_codigo', 'identificador', 'identificadores', 'serie', 'contraparte', 'referencia', 'notas', 'motivo', 'operador', 'accion', 'detalle'], array_keys(app(CatalogoReportes::class)->columns($clase)));
            $pattern = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($query) use ($fields, $pattern) {
                foreach ($fields as $field) {
                    $query->orWhere('r.'.$field, 'like', $pattern);
                }
            });
        }

        return match ($clase) {
            'stock' => $query->orderBy('producto')->orderBy('ubicacion'),
            'movements' => $query->orderBy('registrado')->orderBy('movimiento'),
            'availability' => $query->orderBy('identificador'),
            'audit' => $query->orderByDesc('evento'),
            'responsibilities' => $query->orderByRaw('vencimiento IS NULL')->orderBy('vencimiento')->orderBy('documento')->orderBy('identificador'),
            default => $query->orderByDesc('fecha')->orderByDesc('_documento_id')->orderBy('linea'),
        };
    }

    private function codigo(string $id, string $prefix): string
    {
        return "CONCAT('RG-', {$prefix}, '-', LPAD({$id}, GREATEST(8,CHAR_LENGTH({$id})), '0'))";
    }

    private function productos(Builder $q, string $producto = 'x.producto_id', string $ubicacion = 'x.ubicacion_id'): Builder
    {
        return $q->join('productos as p', 'p.id', '=', $producto)->join('categorias as cat', 'cat.id', '=', 'p.categoria_id')
            ->leftJoin('ubicaciones as l', 'l.id', '=', $ubicacion)
            ->addSelect('p.id as _producto_id', 'p.categoria_id as _categoria_id', 'l.id as _ubicacion_id', 'p.codigo_interno as producto', 'p.nombre as nombre', 'cat.nombre as categoria', 'l.codigo as ubicacion', 'l.nombre as ubicacion_nombre');
    }

    private function identifiers(Builder $q, string $table, string $key, string $line): void
    {
        $identifiers = DB::table($table.' as link')->join('unidades_individuales as u', 'u.id', '=', 'link.unidad_individual_id')
            ->select('link.'.$key)->selectRaw("GROUP_CONCAT(u.identificador ORDER BY u.identificador SEPARATOR ', ') AS identifiers")->groupBy('link.'.$key);
        $q->leftJoinSub($identifiers, 'identities', 'identities.'.$key, '=', $line)->addSelect('identities.identifiers as identificadores');
    }

    private function trabajador(Builder $q, string $documento = 'd'): void
    {
        $q->join('trabajadores as w', 'w.id', '=', $documento.'.trabajador_id')->addSelect($documento.'.trabajador_id as _trabajador_id');
        foreach (['codigo' => 'trabajador_codigo', 'cargo' => 'cargo', 'area' => 'area'] as $field => $alias) {
            $q->selectRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$documento}.datos_historicos_trabajador,'$.{$field}')),w.{$field}) AS {$alias}");
        }
        $q->selectRaw("COALESCE(CONCAT(JSON_UNQUOTE(JSON_EXTRACT({$documento}.datos_historicos_trabajador,'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT({$documento}.datos_historicos_trabajador,'$.apellidos'))), CONCAT(w.nombres,' ',w.apellidos)) AS trabajador");
    }

    private function stock(?string $cutoff): Builder
    {
        $ledger = DB::table('movimientos_inventario')->select('producto_id', 'ubicacion_id')
            ->selectRaw("CAST(SUM(CASE WHEN direccion='in' THEN cantidad ELSE -cantidad END) AS DECIMAL(24,6)) AS cantidad")->groupBy('producto_id', 'ubicacion_id');
        if ($cutoff) {
            $ledger->where('fecha_registro', '<', CarbonImmutable::parse($cutoff, 'America/La_Paz')->addDay()->utc());
        }
        $q = DB::query()->fromSub($ledger, 'x')->select('x.cantidad');
        $this->productos($q);

        return $q->join('unidades_medida as mu', 'mu.id', '=', 'p.unidad_medida_id')->addSelect('mu.simbolo as unidad', 'p.activo as producto_activo');
    }

    private function documento(string $clase): Builder
    {
        [$table, $lineas, $fk, $prefix] = match ($clase) {
            'remittances' => ['documentos_inventario', 'lineas_documento_inventario', 'documento_id', "CASE d.tipo WHEN 'receipt' THEN 'IN' WHEN 'issue' THEN 'EG' ELSE 'AP' END"],
            'deliveries' => ['entregas_materiales', 'lineas_entrega_material', 'entrega_id', "'EN'"],
            'loans' => ['prestamos_herramientas', 'items_prestamo_herramienta', 'prestamo_id', "'PR'"],
            'returns' => ['devoluciones_herramientas', 'items_devolucion_herramienta', 'devolucion_id', "'DV'"],
            'corrections' => ['correcciones_inventario', 'lineas_correccion_inventario', 'correccion_id', "'CO'"],
        };
        $q = DB::table($table.' as d')->join($lineas.' as x', 'x.'.$fk, '=', 'd.id')
            ->leftJoin('usuarios as creador', 'creador.id', '=', 'd.creado_por')
            ->select('d.id as _documento_id', 'd.fecha_documento as fecha', 'd.fecha_documento as _date', 'd.estado as estado', 'creador.nombre as creador')
            ->selectRaw($this->codigo('d.id', $prefix).' AS documento');
        $this->productos($q);
        $sourceColumn = ['remittances' => 'documento_inventario_id', 'deliveries' => 'entrega_id', 'loans' => 'prestamo_id', 'returns' => 'devolucion_id', 'corrections' => 'correccion_destino_id'][$clase];
        $q->leftJoin('compensaciones_documento as dc', 'dc.'.$sourceColumn, '=', 'd.id')->selectRaw('CASE WHEN dc.correccion_id IS NOT NULL THEN '.$this->codigo('dc.correccion_id', "'CO'").' END AS compensado_por');
        if ($clase === 'corrections') {
            $latest = DB::table('autorizaciones_correccion')->select('correccion_id')->selectRaw('MAX(id) AS autorizacion_id')->groupBy('correccion_id');
            $q->leftJoinSub($latest, 'latest', 'latest.correccion_id', '=', 'd.id')->leftJoin('autorizaciones_correccion as authorization', 'authorization.id', '=', 'latest.autorizacion_id')
                ->leftJoin('usuarios as approver', 'approver.id', '=', 'authorization.autorizado_por')
                ->leftJoin('usuarios as executor', 'executor.id', '=', 'd.ejecutado_por')->addSelect('x.id as linea', 'd.tipo as tipo', 'd.motivo as motivo', 'x.direccion as sentido', 'x.cantidad_observada as saldo_observado', 'x.cantidad_contada as conteo', 'x.cantidad as cantidad', 'x.simbolo_unidad as unidad', 'executor.nombre as ejecutor', 'd.fecha_ejecucion as ejecutado', 'x.movimiento_original_id as movimiento_original', 'approver.nombre as autorizador', 'authorization.fecha_autorizacion as autorizado', 'authorization.estado as autorizacion_estado');
            $this->identifiers($q, 'unidades_linea_correccion', 'linea_correccion_id', 'x.id');

            return $q;
        }
        $q->leftJoin('usuarios as confirmer', 'confirmer.id', '=', 'd.confirmado_por')->addSelect('confirmer.nombre as confirmador', 'd.fecha_confirmacion as confirmado', 'd.observaciones as notas');
        if ($clase === 'remittances') {
            $q->addSelect('x.numero_linea as linea', 'd.tipo as tipo', 'd.nombre_contraparte as contraparte', 'd.identificacion_contraparte as contraparte_codigo', 'd.referencia_externa as referencia', 'x.cantidad as cantidad', 'x.simbolo_unidad as unidad');
            $this->identifiers($q, 'unidades_linea_documento', 'linea_documento_id', 'x.id');
        } else {
            $this->trabajador($q);
            if ($clase === 'deliveries') {
                $q->addSelect('x.numero_linea as linea', 'x.cantidad as cantidad', 'x.simbolo_unidad as unidad');
                $this->identifiers($q, 'unidades_linea_entrega', 'linea_entrega_id', 'x.id');
            } else {
                $q->join('unidades_individuales as u', 'u.id', '=', 'x.unidad_individual_id')->addSelect('x.id as linea', 'u.identificador as identificadores')->selectRaw('1 AS cantidad');
                if ($clase === 'loans') {
                    $q->leftJoin('custodias_unidad_individual as custody', 'custody.unidad_individual_id', '=', 'x.unidad_individual_id')->addSelect('x.simbolo_unidad as unidad', 'd.fecha_limite as vencimiento');
                    $q->selectRaw("CASE WHEN d.estado='draft' THEN 'Borrador' WHEN d.estado='cancelled' THEN 'Cancelado' WHEN dc.id IS NOT NULL THEN 'Compensado' WHEN custody.item_prestamo_actual_id=x.id THEN CASE WHEN d.fecha_limite < ? THEN 'Vencido' ELSE 'Pendiente' END ELSE 'Devuelto' END AS situacion", [CarbonImmutable::now('America/La_Paz')->toDateString()]);
                } else {
                    $q->join('items_prestamo_herramienta as original', 'original.id', '=', 'x.item_prestamo_id')->addSelect('original.simbolo_unidad as unidad', 'x.item_prestamo_id as item_prestamo')->selectRaw($this->codigo('d.prestamo_id', "'PR'").' AS prestamo');
                }
            }
        }

        return $q;
    }

    private function responsibilities(): Builder
    {
        $q = DB::table('custodias_unidad_individual as custody')->join('items_prestamo_herramienta as x', 'x.id', '=', 'custody.item_prestamo_actual_id')->join('prestamos_herramientas as d', 'd.id', '=', 'x.prestamo_id')->join('unidades_individuales as u', 'u.id', '=', 'x.unidad_individual_id')
            ->where('d.estado', 'confirmed')->whereNotNull('custody.ciclo_actual_id')
            ->select('u.identificador as identificador', 'u.numero_serie as serie', 'd.fecha_documento as fecha', 'd.fecha_documento as _date', 'd.fecha_limite as vencimiento')->selectRaw($this->codigo('d.id', "'PR'").' AS documento');
        $this->productos($q);
        $this->trabajador($q);
        $q->addSelect('w.activo as trabajador_activo')->selectRaw("CASE WHEN d.fecha_limite < ? THEN 'Vencido' ELSE 'Pendiente' END AS situacion", [CarbonImmutable::now('America/La_Paz')->toDateString()]);

        return $q;
    }

    private function availability(): Builder
    {
        $q = DB::table('posiciones_unidad_individual as x')->join('unidades_individuales as u', 'u.id', '=', 'x.unidad_individual_id')->leftJoin('custodias_unidad_individual as custody', 'custody.unidad_individual_id', '=', 'u.id')->leftJoin('items_prestamo_herramienta as item', 'item.id', '=', 'custody.item_prestamo_actual_id')->leftJoin('prestamos_herramientas as prestamo', 'prestamo.id', '=', 'item.prestamo_id')->leftJoin('trabajadores as w', 'w.id', '=', 'prestamo.trabajador_id')
            ->select('u.identificador as identificador', 'u.numero_serie as serie', 'x.presente as presencia_fisica', 'u.activo as identidad_activa', 'prestamo.fecha_limite as vencimiento')->selectRaw('CASE WHEN prestamo.id IS NOT NULL THEN '.$this->codigo('prestamo.id', "'PR'").' END AS prestamo')
            ->selectRaw("COALESCE(CONCAT(JSON_UNQUOTE(JSON_EXTRACT(prestamo.datos_historicos_trabajador,'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT(prestamo.datos_historicos_trabajador,'$.apellidos'))),CONCAT(w.nombres,' ',w.apellidos)) AS trabajador")
            ->selectRaw("CASE WHEN custody.item_prestamo_actual_id IS NOT NULL THEN 'Prestado' WHEN x.presente=1 THEN 'En almacén' ELSE 'Fuera del almacén' END AS situacion");

        return $this->productos($q)->addSelect('p.activo as producto_activo');
    }

    private function movements(): Builder
    {
        $q = DB::table('movimientos_inventario as x')
            ->leftJoin('lineas_documento_inventario as il', 'il.id', '=', 'x.linea_documento_id')->leftJoin('documentos_inventario as doc', 'doc.id', '=', 'il.documento_id')
            ->leftJoin('lineas_entrega_material as dl', 'dl.id', '=', 'x.linea_entrega_id')->leftJoin('entregas_materiales as delivery', 'delivery.id', '=', 'dl.entrega_id')
            ->leftJoin('items_prestamo_herramienta as li', 'li.id', '=', 'x.item_prestamo_id')->leftJoin('prestamos_herramientas as prestamo', 'prestamo.id', '=', 'li.prestamo_id')
            ->leftJoin('items_devolucion_herramienta as ri', 'ri.id', '=', 'x.item_devolucion_id')->leftJoin('devoluciones_herramientas as returned', 'returned.id', '=', 'ri.devolucion_id')->leftJoin('items_prestamo_herramienta as rli', 'rli.id', '=', 'ri.item_prestamo_id')
            ->leftJoin('lineas_correccion_inventario as cl', 'cl.id', '=', 'x.linea_correccion_id')->leftJoin('correcciones_inventario as correccion', 'correccion.id', '=', 'cl.correccion_id')
            ->join('usuarios as operator', 'operator.id', '=', 'x.creado_por')
            ->select('x.id as movimiento', 'x.fecha_registro as registrado', 'x.fecha_registro as _date', 'x.direccion as sentido', 'x.cantidad as cantidad', 'operator.nombre as operador', 'correccion.tipo as _tipo_correccion', 'x.movimiento_compensado_id as movimiento_original')
            ->selectRaw('COALESCE(il.numero_linea,dl.numero_linea,li.id,ri.id,cl.id) AS linea, COALESCE(il.simbolo_unidad,dl.simbolo_unidad,li.simbolo_unidad,rli.simbolo_unidad,cl.simbolo_unidad) AS unidad')
            ->selectRaw("SUM(CASE WHEN x.direccion='in' THEN x.cantidad ELSE -x.cantidad END) OVER (PARTITION BY x.producto_id,x.ubicacion_id ORDER BY x.fecha_registro,x.id ROWS UNBOUNDED PRECEDING) AS saldo")
            ->selectRaw('COALESCE('.$this->codigo('doc.id', "CASE doc.tipo WHEN 'receipt' THEN 'IN' WHEN 'issue' THEN 'EG' ELSE 'AP' END").','.$this->codigo('delivery.id', "'EN'").','.$this->codigo('prestamo.id', "'PR'").','.$this->codigo('returned.id', "'DV'").','.$this->codigo('correccion.id', "'CO'").') AS documento')
            ->selectRaw("CASE WHEN doc.id IS NOT NULL THEN 'Remito / apertura' WHEN delivery.id IS NOT NULL THEN 'Entrega' WHEN prestamo.id IS NOT NULL THEN 'Préstamo' WHEN returned.id IS NOT NULL THEN 'Devolución' ELSE 'Corrección' END AS origen")
            ->selectRaw("CONCAT(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(delivery.datos_historicos_trabajador,prestamo.datos_historicos_trabajador,returned.datos_historicos_trabajador),'$.nombres')),' ',JSON_UNQUOTE(JSON_EXTRACT(COALESCE(delivery.datos_historicos_trabajador,prestamo.datos_historicos_trabajador,returned.datos_historicos_trabajador),'$.apellidos'))) AS trabajador");
        $this->productos($q);
        // Aggregate identities once per origen line so a cantidad is never multiplied by its identities.
        $parts = [];
        foreach ([['unidades_linea_documento', 'linea_documento_id', 'x.linea_documento_id'], ['unidades_linea_entrega', 'linea_entrega_id', 'x.linea_entrega_id'], ['unidades_linea_correccion', 'linea_correccion_id', 'x.linea_correccion_id']] as $i => [$table, $key, $column]) {
            $sub = DB::table($table.' as link')->join('unidades_individuales as unidad', 'unidad.id', '=', 'link.unidad_individual_id')->select('link.'.$key)->selectRaw("GROUP_CONCAT(unidad.identificador ORDER BY unidad.identificador SEPARATOR ', ') AS ids")->groupBy('link.'.$key);
            $q->leftJoinSub($sub, 'ids'.$i, 'ids'.$i.'.'.$key, '=', $column);
            $parts[] = 'ids'.$i.'.ids';
        }
        $q->leftJoin('unidades_individuales as loan_unit', 'loan_unit.id', '=', 'li.unidad_individual_id')->leftJoin('unidades_individuales as return_unit', 'return_unit.id', '=', 'ri.unidad_individual_id');

        return $q->selectRaw('COALESCE('.implode(',', $parts).',loan_unit.identificador,return_unit.identificador) AS identificadores');
    }
}
