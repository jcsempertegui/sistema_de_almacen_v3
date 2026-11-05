<?php

namespace Tests\Support;

use App\Actions\Catalogo\GuardarProducto;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\Usuario;

trait CreatesProductCatalog
{
    use CreatesTechnicalCatalog;

    private function productData(array $changes = []): array
    {
        return array_replace(['codigo_interno' => 'P-'.strtoupper(bin2hex(random_bytes(4))), 'nombre' => 'Producto técnico', 'descripcion' => null, 'categoria_id' => Categoria::factory()->create()->id, 'unidad_medida_id' => UnidadMedida::factory()->create()->id, 'marca' => null, 'referencia_fabricante' => null, 'modo_control' => 'quantity', 'prestable' => 0, 'activo' => 1, 'values' => []], $changes);
    }

    private function productPayload(Producto $producto, array $changes = []): array
    {
        return array_replace(array_intersect_key($producto->getAttributes(), array_flip($producto->getFillable())), ['version_bloqueo' => $producto->version_bloqueo, 'values' => []], $changes);
    }

    private function savedProduct(Usuario $actor, array $data = []): Producto
    {
        return app(GuardarProducto::class)->handle($actor, null, $this->productData($data));
    }
}
