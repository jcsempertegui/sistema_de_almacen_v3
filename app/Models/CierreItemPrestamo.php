<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreItemPrestamo extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'cierres_item_prestamo';

    protected $primaryKey = 'item_prestamo_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El cierre confirmado se conserva.'));
        static::deleting(fn () => throw new \LogicException('El cierre confirmado se conserva.'));
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ItemDevolucionHerramienta::class, 'item_devolucion_id');
    }
}
