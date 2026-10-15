<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ProductReferences
{
    public function hasHistory(Product $product): bool
    {
        return $product->attributeValues()->lockForUpdate()->first(['id']) !== null
            || $product->productLocations()->lockForUpdate()->first(['id']) !== null
            || $this->hasAssignedUnits($product->id);
    }

    public function hasAssignedUnits(int $productId, ?int $locationId = null): bool
    {
        if (! Schema::hasTable('individual_units')) {
            return false;
        }
        $columns = $locationId === null ? ['product_id'] : ['product_id', 'location_id'];
        if (! Schema::hasColumns('individual_units', $columns)) {
            throw ValidationException::withMessages(['is_active' => 'No se pudo verificar la dependencia de unidades asignadas.']);
        }

        return DB::table('individual_units')->where('product_id', $productId)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->lockForUpdate()->first(['product_id']) !== null;
    }
}
