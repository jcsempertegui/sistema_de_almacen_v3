<?php

namespace App\Models;

use App\Casts\DatosHistoricosEnEspanol;
use Illuminate\Database\Eloquent\Model;

class EventoRegularizacionDevolucion extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'eventos_regularizacion_devolucion';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['detalle' => DatosHistoricosEnEspanol::class, 'fecha_creacion' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial no se modifica.'));
        static::deleting(fn () => throw new \LogicException('El historial no se elimina.'));
    }
}
