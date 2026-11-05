<?php

namespace Database\Factories;

use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ubicacion> */
class UbicacionFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo' => fake()->unique()->bothify('LOC-########'), 'nombre' => fake()->words(3, true), 'tipo' => 'sector', 'padre_id' => null, 'permite_almacenar' => true, 'descripcion' => null, 'activo' => true];
    }
}
