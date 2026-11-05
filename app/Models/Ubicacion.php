<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['codigo', 'nombre', 'tipo', 'padre_id', 'permite_almacenar', 'descripcion', 'activo'])]
class Ubicacion extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'ubicaciones';

    use HasFactory;

    public const TYPES = ['sector' => 'Sector', 'shelf' => 'Estante', 'cabinet' => 'Gabinete'];

    protected $attributes = ['activo' => true, 'permite_almacenar' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'permite_almacenar' => 'boolean', 'padre_id' => 'integer'];
    }

    public function setCodigoAttribute(string $value): void
    {
        $this->attributes['codigo'] = Str::upper(trim($value));
    }

    public function setNombreAttribute(string $value): void
    {
        $this->attributes['nombre'] = trim($value);
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    public function ubicacionesProducto(): HasMany
    {
        return $this->hasMany(UbicacionProducto::class);
    }

    public function unidadesIndividuales(): HasMany
    {
        return $this->hasMany(UnidadIndividual::class);
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
