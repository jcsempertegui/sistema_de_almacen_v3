<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MeasurementUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['internal_code' => 'PR-'.strtoupper(bin2hex(random_bytes(5))), 'name' => 'Producto de prueba', 'category_id' => Category::factory(), 'measurement_unit_id' => MeasurementUnit::factory(), 'control_mode' => 'quantity', 'is_loanable' => false, 'is_active' => true];
    }
}
