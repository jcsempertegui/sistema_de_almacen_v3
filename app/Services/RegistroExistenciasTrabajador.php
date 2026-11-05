<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegistroExistenciasTrabajador
{
    public function post(Usuario $actor, array $line, array $unidades, string $origen, int $sourceId, bool $incoming = false, ?int $itemPrestamo = null): void
    {
        if (DB::transactionLevel() === 0 || ! in_array($origen, ['linea_entrega_id', 'item_prestamo_id', 'item_devolucion_id'], true)) {
            throw new \LogicException('La contabilización requiere una transacción y una fuente válida.');
        }
        $time = now();
        $cantidad = (string) $line['cantidad'];
        $stock = DB::table('existencias_ubicacion')->where('producto_id', $line['producto_id'])->where('ubicacion_id', $line['ubicacion_id']);
        if (! (clone $stock)->lockForUpdate()->first()) {
            DB::table('existencias_ubicacion')->insert(['producto_id' => $line['producto_id'], 'ubicacion_id' => $line['ubicacion_id'], 'cantidad' => '0', 'version_bloqueo' => 1, 'fecha_creacion' => $time, 'fecha_actualizacion' => $time]);
        }
        $condition = $incoming ? 'cantidad <= CAST(999999999999.999999 AS DECIMAL(18,6))-CAST(? AS DECIMAL(18,6))' : 'cantidad >= CAST(? AS DECIMAL(18,6))';
        $operator = $incoming ? '+' : '-';
        if (DB::update("UPDATE existencias_ubicacion SET cantidad=cantidad {$operator} CAST(? AS DECIMAL(18,6)), version_bloqueo=version_bloqueo+1, fecha_actualizacion=? WHERE producto_id=? AND ubicacion_id=? AND {$condition}", [$cantidad, $time, $line['producto_id'], $line['ubicacion_id'], $cantidad]) !== 1) {
            throw ValidationException::withMessages(['lineas' => $incoming ? 'La recepción excedería la capacidad del saldo.' : 'Existencias insuficientes en la ubicación elegida.']);
        }
        sort($unidades);
        foreach ($unidades as $id) {
            $custody = DB::table('custodias_unidad_individual')->where('unidad_individual_id', $id)->lockForUpdate()->first();
            if (($itemPrestamo === null && $custody?->item_prestamo_actual_id !== null) || ($itemPrestamo !== null && (int) ($custody?->item_prestamo_actual_id ?? 0) !== $itemPrestamo)) {
                throw ValidationException::withMessages(['lineas' => 'La custodia de la herramienta no permite esta operación.']);
            }
            $position = DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $id)->lockForUpdate()->first();
            if (($incoming && $position?->presente) || (! $incoming && (! $position?->presente || (int) $position->ubicacion_id !== (int) $line['ubicacion_id']))) {
                throw ValidationException::withMessages(['lineas' => 'La identidad no está disponible físicamente para esta operación.']);
            }
            $values = ['producto_id' => $line['producto_id'], 'presente' => $incoming, 'ubicacion_id' => $incoming ? $line['ubicacion_id'] : null, 'ultima_linea_documento_id' => null, 'ultima_linea_entrega_id' => null, 'ultimo_item_prestamo_id' => null, 'ultimo_item_devolucion_id' => null, ['linea_documento_id' => 'ultima_linea_documento_id', 'linea_entrega_id' => 'ultima_linea_entrega_id', 'item_prestamo_id' => 'ultimo_item_prestamo_id', 'item_devolucion_id' => 'ultimo_item_devolucion_id'][$origen] => $sourceId, 'version_bloqueo' => ($position?->version_bloqueo ?? 0) + 1, 'fecha_creacion' => $position?->fecha_creacion ?? $time, 'fecha_actualizacion' => $time];
            if (Schema::hasColumn('posiciones_unidad_individual', 'ultima_linea_correccion_id')) {
                $values['ultima_linea_correccion_id'] = null;
            }
            if (Schema::hasColumn('posiciones_unidad_individual', 'direccion_ultima_correccion')) {
                $values['direccion_ultima_correccion'] = null;
            }
            DB::table('posiciones_unidad_individual')->updateOrInsert(['unidad_individual_id' => $id], $values);
        }
        DB::table('movimientos_inventario')->insert([$origen => $sourceId, 'producto_id' => $line['producto_id'], 'ubicacion_id' => $line['ubicacion_id'], 'cantidad' => $cantidad, 'direccion' => $incoming ? 'in' : 'out', 'creado_por' => $actor->id, 'fecha_registro' => $time]);
    }
}
