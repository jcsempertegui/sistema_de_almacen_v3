<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class LineaEntregaMaterial extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'lineas_entrega_material';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $m) {
            if (EntregaMaterial::findOrFail($m->entrega_id)->estado !== 'draft') {
                throw new LogicException('Las líneas confirmadas se conservan.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(EntregaMaterial::class, 'entrega_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(UnidadIndividual::class, 'unidades_linea_entrega', 'linea_entrega_id', 'unidad_individual_id');
    }
}
