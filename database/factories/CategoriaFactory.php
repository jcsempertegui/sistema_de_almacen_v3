<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CategoriaFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo' => fake()->unique()->bothify('CAT-########'), 'nombre' => fake()->unique()->sentence(4), 'descripcion' => null, 'activo' => true];
    }
}
