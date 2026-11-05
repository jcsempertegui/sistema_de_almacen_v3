<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosicionUnidadIndividual extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'posiciones_unidad_individual';

    protected $primaryKey = 'unidad_individual_id';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['presente' => 'boolean', 'version_bloqueo' => 'integer'];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(UnidadIndividual::class, 'unidad_individual_id');
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function lastLine(): BelongsTo
    {
        return $this->belongsTo(LineaDocumentoInventario::class, 'ultima_linea_documento_id');
    }
}
