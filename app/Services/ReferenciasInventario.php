<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReferenciasInventario
{
    public function hasStock(int $productId, ?int $locationId = null): bool
    {
        return Schema::hasTable('existencias_ubicacion') && DB::table('existencias_ubicacion')->where('producto_id', $productId)
            ->when($locationId !== null, fn ($q) => $q->where('ubicacion_id', $locationId))
            ->where('cantidad', '>', 0)->lockForUpdate()->first(['id']) !== null;
    }

    public function protectPair(int $productId, int $locationId): void
    {
        if ($this->hasStock($productId, $locationId)) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivar una asociación con existencias físicas.']);
        }
    }

    public function protectUnit(int $unitId): void
    {
        if (Schema::hasTable('posiciones_unidad_individual') && DB::table('posiciones_unidad_individual')->where('unidad_individual_id', $unitId)->where('presente', true)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['activo' => 'No puedes desactivar una unidad físicamente presente. Registra primero su egreso autorizado.']);
        }
    }

    public function hasProductHistory(int $productId): bool
    {
        foreach (['lineas_documento_inventario', 'lineas_entrega_material', 'items_prestamo_herramienta', 'items_devolucion_herramienta', 'lineas_correccion_inventario'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('producto_id', $productId)->lockForUpdate()->first(['id']) !== null) {
                return true;
            }
        }

        return false;
    }
}
