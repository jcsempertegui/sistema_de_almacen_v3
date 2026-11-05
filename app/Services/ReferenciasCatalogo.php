<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReferenciasCatalogo
{
    // Read-only integration points for later stories. No future table/model is
    // created here. Once present, incompatible schemas fail closed.
    private function exists(string $table, string $foreignKey, int $id, bool $activeOnly): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        if (! Schema::hasColumns($table, [$foreignKey, 'activo'])) {
            throw ValidationException::withMessages(['activo' => 'No se pudo verificar una dependencia del catálogo. Revisa su esquema antes de continuar.']);
        }

        return DB::table($table)->where($foreignKey, $id)->when($activeOnly, fn ($query) => $query->where('activo', true))->lockForUpdate()->first([$foreignKey]) !== null;
    }

    public function categoryHasActiveProducts(int $id): bool
    {
        return $this->exists('productos', 'categoria_id', $id, true);
    }

    public function unitHasActiveReferences(int $id): bool
    {
        return $this->exists('productos', 'unidad_medida_id', $id, true) || $this->exists('atributos', 'unidad_medida_id', $id, true);
    }

    public function unitHasAnyReferences(int $id): bool
    {
        return $this->exists('productos', 'unidad_medida_id', $id, false) || $this->exists('atributos', 'unidad_medida_id', $id, false);
    }
}
