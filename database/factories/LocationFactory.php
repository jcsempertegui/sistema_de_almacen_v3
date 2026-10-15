<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('LOC-########'), 'name' => fake()->words(3, true), 'type' => 'sector', 'parent_id' => null, 'is_storable' => true, 'description' => null, 'is_active' => true];
    }
}
