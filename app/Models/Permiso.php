<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['codigo', 'nombre', 'descripcion'])]
class Permiso extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'permisos';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class)->withPivot('activo')->withTimestamps();
    }
}
