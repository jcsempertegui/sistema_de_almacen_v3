<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductLocationFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'location_id' => Location::factory(), 'is_active' => true];
    }
}
