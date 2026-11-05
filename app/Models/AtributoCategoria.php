<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['atributo_id', 'obligatorio', 'identificacion_tecnica', 'orden_presentacion', 'valor_minimo', 'valor_maximo', 'activo'])]
class AtributoCategoria extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'atributos_categoria';

    protected $attributes = ['activo' => true, 'obligatorio' => false, 'identificacion_tecnica' => false, 'orden_presentacion' => 0];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'obligatorio' => 'boolean', 'identificacion_tecnica' => 'boolean', 'orden_presentacion' => 'integer', 'valor_minimo' => 'decimal:6', 'valor_maximo' => 'decimal:6'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function atributo(): BelongsTo
    {
        return $this->belongsTo(AtributoCatalogo::class, 'atributo_id');
    }

    public function valoresAtributosProducto(): HasMany
    {
        return $this->hasMany(ValorAtributoProducto::class);
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
