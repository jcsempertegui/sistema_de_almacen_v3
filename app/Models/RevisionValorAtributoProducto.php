<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RevisionValorAtributoProducto extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'revisiones_valor_atributo';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valor_decimal' => 'decimal:6', 'valor_booleano' => 'boolean', 'valor_fecha' => 'date:Y-m-d', 'activo' => 'boolean', 'fecha_creacion' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las revisiones no se modifican.'));
        static::deleting(fn () => throw new LogicException('Las revisiones no se eliminan.'));
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(ValorAtributoProducto::class, 'valor_atributo_producto_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }
}
