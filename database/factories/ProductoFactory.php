<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductoFactory extends Factory
{
    public function definition(): array
    {
        return ['codigo_interno' => 'PR-'.strtoupper(bin2hex(random_bytes(5))), 'nombre' => 'Producto de prueba', 'categoria_id' => Categoria::factory(), 'unidad_medida_id' => UnidadMedida::factory(), 'modo_control' => 'quantity', 'prestable' => false, 'activo' => true];
    }
}
