<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionHerramienta extends DocumentoOperacionTrabajador
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'devoluciones_herramientas';

    protected $prefix = 'DV';

    public function lineas(): HasMany
    {
        return $this->hasMany(ItemDevolucionHerramienta::class, 'devolucion_id')->orderBy('id');
    }

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(PrestamoHerramienta::class, 'prestamo_id');
    }
}
