<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['codigo', 'nombre', 'tipo_dato', 'unidad_medida_id', 'texto_ayuda', 'activo'])]
class AtributoCatalogo extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'atributos';

    protected $attributes = ['activo' => true];

    public const TYPES = ['text' => 'Texto', 'decimal' => 'Decimal', 'boolean' => 'Booleano', 'date' => 'Fecha'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'unidad_medida_id' => 'integer'];
    }

    public function setCodigoAttribute(string $value): void
    {
        $this->attributes['codigo'] = Str::upper(trim($value));
    }

    public function setNombreAttribute(string $value): void
    {
        $this->attributes['nombre'] = trim($value);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function atributosCategoria(): HasMany
    {
        return $this->hasMany(AtributoCategoria::class, 'atributo_id');
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
