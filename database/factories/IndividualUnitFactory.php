<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class IndividualUnitFactory extends Factory
{
    public function definition(): array
    {
        return ['identifier' => 'IND-'.strtoupper(bin2hex(random_bytes(8))), 'product_id' => Product::factory()->state(['control_mode' => 'individual']), 'serial_number' => null, 'location_id' => null, 'notes' => null, 'is_active' => true];
    }
}
