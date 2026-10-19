<?php

namespace Tests\Support;

use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Models\IndividualUnit;
use App\Models\InventoryDocument;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\User;

trait CreatesInventory
{
    private function inventoryContext(string $mode = 'quantity'): array
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $product = Product::factory()->create(['control_mode' => $mode]);
        $location = Location::factory()->create();
        ProductLocation::factory()->create(['product_id' => $product->id, 'location_id' => $location->id]);
        $unit = $mode === 'individual' ? IndividualUnit::factory()->create(['product_id' => $product->id, 'location_id' => null]) : null;

        return compact('actor', 'product', 'location', 'unit');
    }

    private function inventoryData(array $context, string $kind = 'receipt', string $quantity = '3'): array
    {
        return ['kind' => $kind, 'document_date' => '2026-10-19', 'cutoff_date' => $kind === 'opening' ? '2026-10-18' : null, 'counterparty_id' => $kind === 'opening' ? null : 'EXT-001', 'counterparty_name' => $kind === 'opening' ? null : 'Contraparte de prueba', 'external_reference' => null, 'lines' => [['product_id' => $context['product']->id, 'location_id' => $context['location']->id, 'quantity' => $quantity, 'unit_ids' => $context['unit'] ? [$context['unit']->id] : []]], 'expected_line_count' => 1];
    }

    private function inventoryDraft(array $context, string $kind = 'receipt', string $quantity = '3'): InventoryDocument
    {
        return app(SaveInventoryDocument::class)->handle($context['actor'], null, $this->inventoryData($context, $kind, $quantity));
    }

    private function postInventory(array $context, string $kind = 'receipt', string $quantity = '3'): InventoryDocument
    {
        $document = $this->inventoryDraft($context, $kind, $quantity);

        return app(ConfirmInventoryDocument::class)->handle($context['actor'], $document, $document->lock_version);
    }
}
