<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CiclosPrestamo
{
    public function enabled(): bool
    {
        return Schema::hasTable('ciclos_custodia_prestamo');
    }

    public function open(object $item, ?int $correccion = null, ?int $previous = null): int
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Se requiere transacción.');
        }
        $extra = [];
        if ($correccion !== null) {
            $movement = DB::table('movimientos_inventario')->where('linea_correccion_id', $correccion)->firstOrFail();
            $original = DB::table('movimientos_inventario')->where('id', $movement->movimiento_compensado_id)->firstOrFail();
            $extra = ['movimiento_devolucion_revertido_id' => $original->id, 'item_devolucion_origen_id' => $original->item_devolucion_id];
        }

        return DB::table('ciclos_custodia_prestamo')->insertGetId($extra + ['item_prestamo_id' => $item->id, 'unidad_individual_id' => $item->unidad_individual_id, 'producto_id' => $item->producto_id, 'item_prestamo_apertura_id' => $correccion === null ? $item->id : null, 'linea_correccion_apertura_id' => $correccion, 'ciclo_anterior_id' => $previous, 'fecha_creacion' => now()]);
    }

    public function current(int $unidad, int $itemPrestamo): object
    {
        $custody = DB::table('custodias_unidad_individual')->where('unidad_individual_id', $unidad)->lockForUpdate()->first();
        $cycle = $custody?->ciclo_actual_id ? DB::table('ciclos_custodia_prestamo')->where('id', $custody->ciclo_actual_id)->lockForUpdate()->first() : null;
        if (! $cycle || (int) $cycle->item_prestamo_id !== $itemPrestamo || DB::table('cierres_ciclo_prestamo')->where('ciclo_id', $cycle->id)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['lineas' => 'La herramienta no tiene este ciclo de préstamo pendiente.']);
        }

        return $cycle;
    }

    public function close(object $cycle, ?int $returnItem, ?int $correccion, ?int $authorization = null): void
    {
        DB::table('cierres_ciclo_prestamo')->insert(['ciclo_id' => $cycle->id, 'item_prestamo_id' => $cycle->item_prestamo_id, 'unidad_individual_id' => $cycle->unidad_individual_id, 'producto_id' => $cycle->producto_id, 'item_devolucion_id' => $returnItem, 'linea_correccion_id' => $correccion, 'autorizacion_regularizacion_id' => $authorization, 'fecha_creacion' => now()]);
    }
}
