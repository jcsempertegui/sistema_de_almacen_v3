<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class LineaDocumentoInventario extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'lineas_documento_inventario';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        $draftOnly = function (self $line) {
            $documento = DocumentoInventario::whereKey($line->documento_id)->lockForUpdate()->firstOrFail();
            if ($documento->estado !== 'draft') {
                throw new LogicException('Las líneas de documentos cerrados se conservan sin modificaciones.');
            }
        };
        static::creating($draftOnly);
        static::updating($draftOnly);
        static::deleting($draftOnly);
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoInventario::class, 'documento_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }

    public function unidades(): BelongsToMany
    {
        return $this->belongsToMany(UnidadIndividual::class, 'unidades_linea_documento', 'linea_documento_id', 'unidad_individual_id');
    }
}
