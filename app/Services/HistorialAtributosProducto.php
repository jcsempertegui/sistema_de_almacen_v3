<?php

namespace App\Services;

use App\Models\RevisionValorAtributoProducto;
use App\Models\Usuario;
use App\Models\ValorAtributoProducto;

class HistorialAtributosProducto
{
    // Called only while holding the aggregate lock inside ModificacionSegura.
    public function record(ValorAtributoProducto $value, Usuario $actor): void
    {
        $snapshot = array_intersect_key($value->getAttributes(), array_flip([...ValorAtributoProducto::VALUE_COLUMNS, 'activo']));
        RevisionValorAtributoProducto::create($snapshot + [
            'valor_atributo_producto_id' => $value->id,
            'revision' => ($value->revisiones()->max('revision') ?? 0) + 1,
            'creado_por' => $actor->id,
            'fecha_creacion' => now(),
        ]);
    }
}
