<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo'])]
class UnidadMedida extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'unidades_medida';

    use HasFactory;

    public const DIMENSIONS = ['count' => 'Conteo', 'length' => 'Longitud', 'mass' => 'Masa', 'volume' => 'Volumen', 'time' => 'Tiempo', 'other' => 'Otra'];

    protected $attributes = ['activo' => true, 'decimales' => 0];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'decimales' => 'integer'];
    }

    public function setCodigoAttribute(string $value): void
    {
        $this->attributes['codigo'] = Str::upper(trim($value));
    }

    public function setNombreAttribute(string $value): void
    {
        $this->attributes['nombre'] = trim($value);
    }

    public function setSimboloAttribute(string $value): void
    {
        $this->attributes['simbolo'] = trim($value);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function atributos(): HasMany
    {
        return $this->hasMany(AtributoCatalogo::class, 'unidad_medida_id');
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'modificado_por');
    }
}
