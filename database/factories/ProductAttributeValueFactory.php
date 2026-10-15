<?php

namespace Database\Factories;

use App\Models\CatalogAttribute;
use App\Models\CategoryAttribute;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductAttributeValueFactory extends Factory
{
    public function definition(): array
    {
        $product = Product::factory()->create();
        $attribute = CatalogAttribute::create(['code' => 'T-'.bin2hex(random_bytes(5)), 'name' => 'Texto', 'data_type' => 'text', 'is_active' => true]);
        $association = new CategoryAttribute(['attribute_id' => $attribute->id, 'is_active' => true]);
        $association->category_id = $product->category_id;
        $association->save();

        return ['product_id' => $product->id, 'category_id' => $product->category_id, 'category_attribute_id' => $association->id, 'value_text' => 'Valor', 'is_active' => true];
    }
}
