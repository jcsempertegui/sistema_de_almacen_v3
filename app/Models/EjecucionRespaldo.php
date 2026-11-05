<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EjecucionRespaldo extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'ejecuciones_respaldo';

    protected $guarded = ['*'];

    protected $hidden = ['nombre_archivo', 'fallo'];

    protected function casts(): array
    {
        return ['manifiesto' => 'array', 'fecha_finalizacion' => 'datetime'];
    }
}
