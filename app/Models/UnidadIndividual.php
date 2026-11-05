<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['identificador', 'producto_id', 'numero_serie', 'ubicacion_id', 'observaciones', 'activo'])]
class UnidadIndividual extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'unidades_individuales';

    use HasFactory;

    protected $attributes = ['activo' => true, 'version_bloqueo' => 1];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'version_bloqueo' => 'integer'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function position(): HasOne
    {
        return $this->hasOne(PosicionUnidadIndividual::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'modificado_por');
    }
}
