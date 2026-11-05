<?php

namespace App\Models;

use App\Casts\DatosHistoricosEnEspanol;
use App\Models\Concerns\TieneCompensacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

abstract class DocumentoOperacionTrabajador extends Model
{
    use TieneCompensacion;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['datos_historicos_trabajador' => DatosHistoricosEnEspanol::class, 'fecha_documento' => 'date', 'fecha_limite' => 'date', 'fecha_confirmacion' => 'datetime', 'fecha_cancelacion' => 'datetime', 'version_bloqueo' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $m) {
            if ($m->getRawOriginal('estado') !== 'draft') {
                throw new LogicException('El documento confirmado o cancelado es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los documentos se conservan. Cancela el borrador.'));
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function getCodigoAttribute(): string
    {
        return 'RG-'.$this->prefix.'-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }
}
