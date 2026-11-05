<?php

namespace Database\Factories;

use App\Models\AtributoCatalogo;
use App\Models\AtributoCategoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

class ValorAtributoProductoFactory extends Factory
{
    public function definition(): array
    {
        $producto = Producto::factory()->create();
        $atributo = AtributoCatalogo::create(['codigo' => 'T-'.bin2hex(random_bytes(5)), 'nombre' => 'Texto', 'tipo_dato' => 'text', 'activo' => true]);
        $association = new AtributoCategoria(['atributo_id' => $atributo->id, 'activo' => true]);
        $association->categoria_id = $producto->categoria_id;
        $association->save();

        return ['producto_id' => $producto->id, 'categoria_id' => $producto->categoria_id, 'atributo_categoria_id' => $association->id, 'valor_texto' => 'Valor', 'activo' => true];
    }
}
