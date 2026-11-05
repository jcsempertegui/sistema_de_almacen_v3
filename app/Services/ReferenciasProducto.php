<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReferenciasProducto
{
    public function hasActiveUnits(int $productId): bool
    {
        if (! Schema::hasTable('unidades_individuales')) {
            return false;
        }

        return DB::table('unidades_individuales')->where('producto_id', $productId)->where('activo', true)->lockForUpdate()->first(['id']) !== null;
    }

    public function hasHistory(Producto $producto): bool
    {
        return app(ReferenciasInventario::class)->hasProductHistory($producto->id)
            || $producto->valoresAtributos()->lockForUpdate()->first(['id']) !== null
            || $producto->ubicacionesProducto()->lockForUpdate()->first(['id']) !== null
            || $this->hasAssignedUnits($producto->id);
    }

    public function hasAssignedUnits(int $productId, ?int $locationId = null): bool
    {
        if (! Schema::hasTable('unidades_individuales')) {
            return false;
        }
        $columns = $locationId === null ? ['producto_id'] : ['producto_id', 'ubicacion_id'];
        if (! Schema::hasColumns('unidades_individuales', $columns)) {
            throw ValidationException::withMessages(['activo' => 'No se pudo verificar la dependencia de unidades asignadas.']);
        }

        return DB::table('unidades_individuales')->where('producto_id', $productId)
            ->when($locationId !== null, fn ($q) => $q->where('ubicacion_id', $locationId))
            ->lockForUpdate()->first(['producto_id']) !== null;
    }
}
