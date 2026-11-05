<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ItemDevolucionHerramienta extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'items_devolucion_herramienta';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $m) {
            if (DevolucionHerramienta::findOrFail($m->devolucion_id)->estado !== 'draft') {
                throw new LogicException('Los ítems confirmados se conservan.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DevolucionHerramienta::class, 'devolucion_id');
    }

    public function itemPrestamo(): BelongsTo
    {
        return $this->belongsTo(ItemPrestamoHerramienta::class, 'item_prestamo_id');
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadIndividual::class, 'unidad_individual_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function autorizaciones(): HasMany
    {
        return $this->hasMany(AutorizacionRegularizacionDevolucion::class, 'item_devolucion_id');
    }
}
