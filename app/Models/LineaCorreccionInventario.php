<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LineaCorreccionInventario extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'lineas_correccion_inventario';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $line) {
            if ($line->documento()->firstOrFail()->estado !== 'draft') {
                throw new \LogicException('Los detalles solicitados o ejecutados se conservan.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function documento()
    {
        return $this->belongsTo(CorreccionInventario::class, 'correccion_id');
    }

    public function unidades()
    {
        return $this->belongsToMany(UnidadIndividual::class, 'unidades_linea_correccion', 'linea_correccion_id', 'unidad_individual_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion()
    {
        return $this->belongsTo(Ubicacion::class);
    }
}
