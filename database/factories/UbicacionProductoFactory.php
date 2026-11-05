<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Ubicacion;
use Illuminate\Database\Eloquent\Factories\Factory;

class UbicacionProductoFactory extends Factory
{
    public function definition(): array
    {
        return ['producto_id' => Producto::factory(), 'ubicacion_id' => Ubicacion::factory(), 'activo' => true];
    }
}
