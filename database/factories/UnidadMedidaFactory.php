<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadMedidaFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo' => fake()->unique()->bothify('MED-########'), 'nombre' => 'Unidad de prueba', 'simbolo' => fake()->unique()->bothify('u########'), 'dimension' => 'count', 'decimales' => 0, 'activo' => true];
    }
}
