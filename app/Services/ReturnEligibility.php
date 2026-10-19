<?php

namespace App\Services;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\ToolLoanItem;
use App\Models\ToolReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReturnEligibility
{
    public function inspect(array $item, ToolReturn $document): array
    {
        $loanItem = ToolLoanItem::whereKey($item['loan_item_id'])->lockForUpdate()->firstOrFail();
        if ($loanItem->loan_id !== $document->loan_id || $loanItem->individual_unit_id !== (int) $item['individual_unit_id'] || $loanItem->product_id !== (int) $item['product_id']) {
            $this->fail('El ítem no pertenece al préstamo.');
        }
        $custody = DB::table('individual_unit_custodies')->where('individual_unit_id', $loanItem->individual_unit_id)->lockForUpdate()->first();
        if ((int) ($custody?->current_loan_item_id ?? 0) !== $loanItem->id || DB::table('loan_item_settlements')->where('loan_item_id', $loanItem->id)->lockForUpdate()->first()) {
            $this->fail('La herramienta no tiene este préstamo pendiente.');
        }
        $product = Product::whereKey($loanItem->product_id)->lockForUpdate()->firstOrFail();
        $unit = IndividualUnit::whereKey($loanItem->individual_unit_id)->lockForUpdate()->firstOrFail();
        $measure = MeasurementUnit::whereKey($loanItem->measurement_unit_id)->lockForUpdate()->firstOrFail();
        if ($product->control_mode !== 'individual' || ! $product->is_loanable || $unit->product_id !== $product->id || $product->measurement_unit_id !== $measure->id || $measure->dimension !== 'count' || $measure->decimal_places !== 0 || $measure->symbol !== $loanItem->unit_symbol) {
            $this->fail('La definición histórica de la herramienta es incompatible; no puede regularizarse reinterpretando cantidades.');
        }
        $location = Location::whereKey($item['location_id'])->lockForUpdate()->first();
        if (! $location?->is_active || ! $location->is_storable) {
            $this->fail('El destino debe estar activo y permitir almacenamiento.');
        }
        app(LocationHierarchy::class)->validateParent($location, true);
        $pair = ProductLocation::where('product_id', $product->id)->where('location_id', $location->id)->lockForUpdate()->first();
        if (! $pair) {
            $this->fail('Debe existir la asociación producto–destino. No se crea automáticamente.');
        }
        $snapshot = ['document_id' => $document->id, 'document_version' => $document->lock_version, 'loan_id' => $document->loan_id, 'loan_item_id' => $loanItem->id, 'individual_unit_id' => $unit->id, 'product_id' => $product->id, 'location_id' => $location->id,
            'product' => $product->only(['is_active', 'lock_version', 'measurement_unit_id', 'control_mode', 'is_loanable']),
            'unit' => $unit->only(['is_active', 'lock_version', 'product_id']), 'measurement' => $measure->only(['is_active', 'symbol', 'dimension', 'decimal_places']),
            'location' => $location->only(['is_active', 'is_storable', 'parent_id', 'lock_version']), 'pair' => $pair->only(['id', 'is_active', 'updated_at'])];
        $exceptions = [];
        foreach (['product' => $product, 'unit' => $unit, 'measurement' => $measure, 'pair' => $pair] as $key => $reference) {
            if (! $reference->is_active) {
                $exceptions[] = $key;
            }
        }

        return ['snapshot' => $snapshot, 'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'exceptions' => $exceptions];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['lines' => $message]);
    }
}
