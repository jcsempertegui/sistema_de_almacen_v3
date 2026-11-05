<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class ItemPrestamoHerramienta extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'items_prestamo_herramienta';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $m) {
            if (PrestamoHerramienta::findOrFail($m->prestamo_id)->estado !== 'draft') {
                throw new LogicException('Los ítems confirmados se conservan.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(PrestamoHerramienta::class, 'prestamo_id');
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

    public function settlement(): HasOne
    {
        return $this->hasOne(CierreItemPrestamo::class, 'item_prestamo_id');
    }

    public function scopePending($query)
    {
        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('custodias_unidad_individual')->whereColumn('custodias_unidad_individual.item_prestamo_actual_id', 'items_prestamo_herramienta.id'));
    }
}
