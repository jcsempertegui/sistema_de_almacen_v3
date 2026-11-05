<?php

namespace App\Models;

use App\Models\Concerns\TieneCompensacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorreccionInventario extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'correcciones_inventario';

    use TieneCompensacion;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['fecha_documento' => 'date', 'fecha_ejecucion' => 'datetime'];
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaCorreccionInventario::class, 'correccion_id')->orderBy('id');
    }

    public function autorizaciones(): HasMany
    {
        return $this->hasMany(AutorizacionCorreccion::class, 'correccion_id')->orderByDesc('id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $m) {
            if ($m->getRawOriginal('estado') === 'executed') {
                throw new \LogicException('La corrección ejecutada se conserva.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las correcciones no se eliminan.'));
    }

    public function getCodigoAttribute(): string
    {
        return 'RG-CO-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }
}
