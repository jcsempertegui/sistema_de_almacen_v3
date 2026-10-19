<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class InventoryReferences
{
    public function hasStock(int $productId, ?int $locationId = null): bool
    {
        return Schema::hasTable('stock_positions') && DB::table('stock_positions')->where('product_id', $productId)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->where('quantity', '>', 0)->lockForUpdate()->first(['id']) !== null;
    }

    public function protectPair(int $productId, int $locationId): void
    {
        if ($this->hasStock($productId, $locationId)) {
            throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una asociación con existencias físicas.']);
        }
    }

    public function protectUnit(int $unitId): void
    {
        if (Schema::hasTable('individual_unit_positions') && DB::table('individual_unit_positions')->where('individual_unit_id', $unitId)->where('is_present', true)->lockForUpdate()->first()) {
            throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una unidad físicamente presente. Registra primero su egreso autorizado.']);
        }
    }

    public function hasProductHistory(int $productId): bool
    {
        return Schema::hasTable('inventory_document_lines') && DB::table('inventory_document_lines')->where('product_id', $productId)->lockForUpdate()->first(['id']) !== null;
    }
}
