<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class CatalogReferences
{
    // Read-only integration points for later stories. No future table/model is
    // created here. Once present, incompatible schemas fail closed.
    private function exists(string $table, string $foreignKey, int $id, bool $activeOnly): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        if (! Schema::hasColumns($table, [$foreignKey, 'is_active'])) {
            throw ValidationException::withMessages(['is_active' => 'No se pudo verificar una dependencia del catálogo. Revisa su esquema antes de continuar.']);
        }

        return DB::table($table)->where($foreignKey, $id)->when($activeOnly, fn ($query) => $query->where('is_active', true))->lockForUpdate()->first([$foreignKey]) !== null;
    }

    public function categoryHasActiveProducts(int $id): bool
    {
        return $this->exists('products', 'category_id', $id, true);
    }

    public function unitHasActiveReferences(int $id): bool
    {
        return $this->exists('products', 'measurement_unit_id', $id, true) || $this->exists('attributes', 'measurement_unit_id', $id, true);
    }

    public function unitHasAnyReferences(int $id): bool
    {
        return $this->exists('products', 'measurement_unit_id', $id, false) || $this->exists('attributes', 'measurement_unit_id', $id, false);
    }
}
