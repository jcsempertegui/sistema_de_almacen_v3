<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['codigo', 'nombre', 'descripcion', 'activo'])]
class Categoria extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'categorias';

    use HasFactory;

    protected $attributes = ['activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function setCodigoAttribute(string $value): void
    {
        $this->attributes['codigo'] = Str::upper(trim($value));
    }

    public function setNombreAttribute(string $value): void
    {
        $this->attributes['nombre'] = trim($value);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    protected function childRouteBindingRelationshipName($childType)
    {
        return $childType === 'atributo_categoria'
            ? 'atributosCategoria'
            : parent::childRouteBindingRelationshipName($childType);
    }

    public function atributosCategoria(): HasMany
    {
        return $this->hasMany(AtributoCategoria::class);
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'modificado_por');
    }
}
