<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MeasurementUnitFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('MED-########'), 'name' => 'Unidad de prueba', 'symbol' => fake()->unique()->bothify('u########'), 'dimension' => 'count', 'decimal_places' => 0, 'is_active' => true];
    }
}
