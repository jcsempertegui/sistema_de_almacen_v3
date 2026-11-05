<?php

namespace Tests\Support;

use App\Actions\Catalogo\GuardarUnidadIndividual;
use App\Models\Producto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;

trait CreatesIndividualUnitCatalog
{
    private function individualData(array $changes = []): array
    {
        return array_replace(['identificador' => 'IND-'.strtoupper(bin2hex(random_bytes(6))), 'producto_id' => $changes['producto_id'] ?? Producto::factory()->create(['modo_control' => 'individual'])->id, 'numero_serie' => null, 'ubicacion_id' => null, 'observaciones' => null, 'activo' => 1], $changes);
    }

    private function individualPayload(UnidadIndividual $unidad, array $changes = []): array
    {
        return array_replace(array_intersect_key($unidad->getAttributes(), array_flip($unidad->getFillable())), ['version_bloqueo' => $unidad->version_bloqueo], $changes);
    }

    private function savedIndividual(Usuario $actor, array $changes = []): UnidadIndividual
    {
        return app(GuardarUnidadIndividual::class)->handle($actor, null, $this->individualData($changes));
    }
}
