<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['codigo_interno', 'nombre', 'descripcion', 'categoria_id', 'unidad_medida_id', 'marca', 'referencia_fabricante', 'modo_control', 'prestable', 'activo'])]
class Producto extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'productos';

    protected function childRouteBindingRelationshipName($childType)
    {
        return $childType === 'ubicacion_producto'
            ? 'ubicacionesProducto'
            : parent::childRouteBindingRelationshipName($childType);
    }

    public function unidadesIndividuales(): HasMany
    {
        return $this->hasMany(UnidadIndividual::class);
    }

    use HasFactory;

    public const MODES = ['quantity' => 'Por cantidad', 'individual' => 'Individual'];

    protected $attributes = ['activo' => true, 'prestable' => false, 'version_bloqueo' => 1];

    public function setCodigoInternoAttribute(string $value): void
    {
        $this->attributes['codigo_interno'] = Str::upper(trim($value));
    }

    public function setNombreAttribute(string $value): void
    {
        $this->attributes['nombre'] = trim($value);
    }

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'prestable' => 'boolean', 'version_bloqueo' => 'integer'];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function valoresAtributos(): HasMany
    {
        return $this->hasMany(ValorAtributoProducto::class);
    }

    public function ubicacionesProducto(): HasMany
    {
        return $this->hasMany(UbicacionProducto::class);
    }

    public function ubicaciones(): BelongsToMany
    {
        return $this->belongsToMany(Ubicacion::class, 'ubicaciones_producto')->withPivot(['id', 'activo', 'creado_por', 'modificado_por'])->withTimestamps();
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
