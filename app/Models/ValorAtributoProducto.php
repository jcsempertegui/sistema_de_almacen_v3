<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['valor_texto', 'valor_decimal', 'valor_booleano', 'valor_fecha', 'activo'])]
class ValorAtributoProducto extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'valores_atributo_producto';

    use HasFactory;

    public static function columnaParaTipo(string $tipo): string
    {
        return ['text' => 'valor_texto', 'decimal' => 'valor_decimal', 'boolean' => 'valor_booleano', 'date' => 'valor_fecha'][$tipo];
    }

    public const VALUE_COLUMNS = ['valor_texto', 'valor_decimal', 'valor_booleano', 'valor_fecha'];

    protected function casts(): array
    {
        return ['valor_decimal' => 'decimal:6', 'valor_booleano' => 'boolean', 'valor_fecha' => 'date:Y-m-d', 'activo' => 'boolean'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function atributoCategoria(): BelongsTo
    {
        return $this->belongsTo(AtributoCategoria::class);
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionValorAtributoProducto::class)->orderBy('revision');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'modificado_por');
    }

    public function valorPresentacion(): string
    {
        if ($this->valor_booleano !== null) {
            return $this->valor_booleano ? 'Sí' : 'No';
        }

        return $this->valor_fecha?->format('Y-m-d') ?? $this->valor_decimal ?? $this->valor_texto ?? '';
    }
}
