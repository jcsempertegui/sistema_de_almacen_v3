<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReferenciasUbicacion
{
    public function hasActiveProductLocations(array $ids): bool
    {
        if (! Schema::hasTable('ubicaciones_producto')) {
            return false;
        }
        if (! Schema::hasColumns('ubicaciones_producto', ['ubicacion_id', 'activo'])) {
            throw ValidationException::withMessages(['activo' => 'No se pudo verificar la configuración de ubicaciones de productos.']);
        }

        return DB::table('ubicaciones_producto')->whereIn('ubicacion_id', $ids)->where('activo', true)->lockForUpdate()->first(['ubicacion_id']) !== null;
    }

    public function hasAssignedUnits(array $ids): bool
    {
        return $this->exists('unidades_individuales', $ids);
    }

    public function hasHistoricalReferences(array $ids): bool
    {
        return $this->hasAssignedUnits($ids) || $this->exists('ubicaciones_producto', $ids) || $this->exists('lineas_documento_inventario', $ids);
    }

    private function exists(string $table, array $ids): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        if (! Schema::hasColumn($table, 'ubicacion_id')) {
            throw ValidationException::withMessages(['ubicacion' => 'No se pudo verificar una dependencia histórica de ubicaciones. Revisa su esquema antes de continuar.']);
        }

        // Includes inactive rows retaining the assignment. Future writers must
        // share the mutex and check the ubicacion state/storability themselves.
        return DB::table($table)->whereIn('ubicacion_id', $ids)->lockForUpdate()->first(['ubicacion_id']) !== null;
    }
}
