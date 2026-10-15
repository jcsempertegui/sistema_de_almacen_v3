<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LocationReferences
{
    public function hasAssignedUnits(array $ids): bool
    {
        return $this->exists('individual_units', $ids);
    }

    public function hasHistoricalReferences(array $ids): bool
    {
        return $this->hasAssignedUnits($ids) || $this->exists('product_locations', $ids);
    }

    private function exists(string $table, array $ids): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        if (! Schema::hasColumn($table, 'location_id')) {
            throw ValidationException::withMessages(['location' => 'No se pudo verificar una dependencia histórica de ubicaciones. Revisa su esquema antes de continuar.']);
        }

        // Includes inactive rows retaining the assignment. Future writers must
        // share the mutex and check the location state/storability themselves.
        return DB::table($table)->whereIn('location_id', $ids)->lockForUpdate()->first(['location_id']) !== null;
    }
}
