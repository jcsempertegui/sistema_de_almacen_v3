<?php

namespace Tests\Support;

use App\Models\AtributoCatalogo;
use App\Models\AtributoCategoria;
use App\Models\Categoria;

trait CreatesTechnicalCatalog
{
    private function attributeData(array $changes = []): array
    {
        return array_replace(['codigo' => 'TEMP', 'nombre' => 'Temperatura', 'tipo_dato' => 'decimal', 'unidad_medida_id' => null, 'texto_ayuda' => 'Temperatura técnica', 'activo' => 1], $changes);
    }

    private function associationData(array $changes = []): array
    {
        return array_replace(['obligatorio' => 0, 'identificacion_tecnica' => 0, 'orden_presentacion' => 0, 'valor_minimo' => null, 'valor_maximo' => null, 'activo' => 1], $changes);
    }

    private function technicalAttribute(array $changes = []): AtributoCatalogo
    {
        return AtributoCatalogo::create($this->attributeData(array_replace(['codigo' => 'ATTR-'.bin2hex(random_bytes(4))], $changes)));
    }

    private function association(Categoria $categoria, AtributoCatalogo $atributo, array $changes = []): AtributoCategoria
    {
        $association = new AtributoCategoria($this->associationData(array_replace(['atributo_id' => $atributo->id], $changes)));
        $association->categoria()->associate($categoria);
        $association->save();

        return $association;
    }
}
