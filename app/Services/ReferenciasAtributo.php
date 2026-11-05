<?php

namespace App\Services;

use App\Models\AtributoCategoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ReferenciasAtributo
{
    public function associationHasValues(int $id): bool
    {
        if (! Schema::hasTable('valores_atributo_producto')) {
            return false;
        }
        $this->requireColumns('valores_atributo_producto', ['atributo_categoria_id']);

        return DB::table('valores_atributo_producto')->where('atributo_categoria_id', $id)->lockForUpdate()->first(['producto_id']) !== null;
    }

    public function categoryHasIncompleteProducts(AtributoCategoria $association): bool
    {
        if (! Schema::hasTable('productos')) {
            return false;
        }
        $this->requireColumns('productos', ['id', 'categoria_id', 'activo']);
        $productos = DB::table('productos')->where('categoria_id', $association->categoria_id)->where('activo', true);
        if (! Schema::hasTable('valores_atributo_producto')) {
            return $productos->lockForUpdate()->first(['id']) !== null;
        }
        $this->requireColumns('valores_atributo_producto', ['producto_id', 'atributo_categoria_id', 'activo']);

        foreach ($productos->lockForUpdate()->get(['id']) as $producto) {
            if (! DB::table('valores_atributo_producto')->where('producto_id', $producto->id)
                ->where('atributo_categoria_id', $association->id)->where('activo', true)->lockForUpdate()->first(['id'])) {
                return true;
            }
        }

        return false;
    }

    private function requireColumns(string $table, array $columns): void
    {
        if (! Schema::hasColumns($table, $columns)) {
            throw ValidationException::withMessages(['atributo_id' => 'No se pudo verificar el historial técnico. Revisa la compatibilidad de las dependencias antes de modificar la configuración.']);
        }
    }
}
