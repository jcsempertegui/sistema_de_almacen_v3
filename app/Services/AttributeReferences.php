<?php

namespace App\Services;

use App\Models\CategoryAttribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttributeReferences
{
    public function associationHasValues(int $id): bool
    {
        if (! Schema::hasTable('product_attribute_values')) {
            return false;
        }
        $this->requireColumns('product_attribute_values', ['category_attribute_id']);

        return DB::table('product_attribute_values')->where('category_attribute_id', $id)->lockForUpdate()->first(['product_id']) !== null;
    }

    public function categoryHasIncompleteProducts(CategoryAttribute $association): bool
    {
        if (! Schema::hasTable('products')) {
            return false;
        }
        $this->requireColumns('products', ['id', 'category_id', 'is_active']);
        $products = DB::table('products')->where('category_id', $association->category_id)->where('is_active', true);
        if (! Schema::hasTable('product_attribute_values')) {
            return $products->lockForUpdate()->first(['id']) !== null;
        }
        $this->requireColumns('product_attribute_values', ['product_id', 'category_attribute_id', 'is_active']);

        foreach ($products->lockForUpdate()->get(['id']) as $product) {
            if (! DB::table('product_attribute_values')->where('product_id', $product->id)
                ->where('category_attribute_id', $association->id)->where('is_active', true)->lockForUpdate()->first(['id'])) {
                return true;
            }
        }

        return false;
    }

    private function requireColumns(string $table, array $columns): void
    {
        if (! Schema::hasColumns($table, $columns)) {
            throw ValidationException::withMessages(['attribute_id' => 'No se pudo verificar el historial técnico. Revisa la compatibilidad de las dependencias antes de modificar la configuración.']);
        }
    }
}
