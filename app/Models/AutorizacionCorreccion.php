<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutorizacionCorreccion extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'autorizaciones_correccion';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['fecha_vencimiento' => 'datetime', 'fecha_autorizacion' => 'datetime', 'fecha_utilizacion' => 'datetime'];
    }
}
