<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutorizacionRegularizacionDevolucion extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'autorizaciones_regularizacion_devolucion';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Utiliza el procedimiento administrativo autorizado.'));
        static::deleting(fn () => throw new \LogicException('Las autorizaciones se conservan.'));
    }

    protected function casts(): array
    {
        return ['datos_historicos_referencia' => 'array', 'fecha_vencimiento' => 'datetime', 'fecha_autorizacion' => 'datetime', 'fecha_utilizacion' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemDevolucionHerramienta::class, 'item_devolucion_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoRegularizacionDevolucion::class, 'autorizacion_id')->orderBy('id');
    }
}
