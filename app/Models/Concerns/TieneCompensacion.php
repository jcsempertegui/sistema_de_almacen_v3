<?php

namespace App\Models\Concerns;

use App\Models\CorreccionInventario;
use App\Models\DevolucionHerramienta;
use App\Models\DocumentoInventario;
use App\Models\EntregaMaterial;
use App\Models\PrestamoHerramienta;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait TieneCompensacion
{
    public function getCompensationIdAttribute(): ?int
    {
        if (! Schema::hasTable('compensaciones_documento')) {
            return null;
        }
        $column = match (static::class) {
            DocumentoInventario::class => 'documento_inventario_id',EntregaMaterial::class => 'entrega_id',PrestamoHerramienta::class => 'prestamo_id',DevolucionHerramienta::class => 'devolucion_id',CorreccionInventario::class => 'correccion_destino_id',default => null
        };

        return $column ? DB::table('compensaciones_documento')->where($column, $this->id)->value('correccion_id') : null;
    }
}
