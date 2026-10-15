<?php

namespace Tests\Support;

use App\Actions\Catalog\SaveProduct;
use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\User;

trait CreatesProductCatalog
{
    use CreatesTechnicalCatalog;

    private function productData(array $changes = []): array
    {
        return array_replace(['internal_code' => 'P-'.strtoupper(bin2hex(random_bytes(4))), 'name' => 'Producto técnico', 'description' => null, 'category_id' => Category::factory()->create()->id, 'measurement_unit_id' => MeasurementUnit::factory()->create()->id, 'brand' => null, 'manufacturer_reference' => null, 'control_mode' => 'quantity', 'is_loanable' => 0, 'is_active' => 1, 'values' => []], $changes);
    }

    private function productPayload(Product $product, array $changes = []): array
    {
        return array_replace(array_intersect_key($product->getAttributes(), array_flip($product->getFillable())), ['lock_version' => $product->lock_version, 'values' => []], $changes);
    }

    private function savedProduct(User $actor, array $data = []): Product
    {
        return app(SaveProduct::class)->handle($actor, null, $this->productData($data));
    }
}
