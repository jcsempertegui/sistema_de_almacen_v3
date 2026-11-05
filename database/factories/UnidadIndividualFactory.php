<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnidadIndividualFactory extends Factory
{
    public function definition(): array
    {
        return ['identificador' => 'IND-'.strtoupper(bin2hex(random_bytes(8))), 'producto_id' => Producto::factory()->state(['modo_control' => 'individual']), 'numero_serie' => null, 'ubicacion_id' => null, 'observaciones' => null, 'activo' => true];
    }
}
