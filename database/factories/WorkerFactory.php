<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo' => fake()->unique()->bothify('TR-########'), 'nombres' => fake()->firstName(), 'apellidos' => fake()->lastName(), 'documento_identidad' => null, 'cargo' => null, 'area' => null, 'is_active' => true];
    }
}
