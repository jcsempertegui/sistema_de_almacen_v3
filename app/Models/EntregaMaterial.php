<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EntregaMaterial extends DocumentoOperacionTrabajador
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'entregas_materiales';

    protected $prefix = 'EN';

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaEntregaMaterial::class, 'entrega_id')->orderBy('numero_linea');
    }
}
