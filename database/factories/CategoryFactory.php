<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['code' => fake()->unique()->bothify('CAT-########'), 'name' => fake()->unique()->sentence(4), 'description' => null, 'is_active' => true];
    }
}
