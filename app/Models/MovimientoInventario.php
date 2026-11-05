<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MovimientoInventario extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'movimientos_inventario';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['fecha_registro' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos confirmados son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los movimientos confirmados no se eliminan.'));
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(LineaDocumentoInventario::class, 'linea_documento_id');
    }

    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(LineaEntregaMaterial::class, 'linea_entrega_id');
    }

    public function itemPrestamo(): BelongsTo
    {
        return $this->belongsTo(ItemPrestamoHerramienta::class, 'item_prestamo_id');
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ItemDevolucionHerramienta::class, 'item_devolucion_id');
    }

    public function getOrigenAttribute(): LineaDocumentoInventario|LineaEntregaMaterial|ItemPrestamoHerramienta|ItemDevolucionHerramienta|LineaCorreccionInventario
    {
        if ($this->linea_correccion_id !== null) {
            return $this->correctionLine;
        }

        return $this->linea_documento_id !== null ? $this->line : ($this->linea_entrega_id !== null ? $this->deliveryLine : ($this->item_prestamo_id !== null ? $this->itemPrestamo : $this->returnItem));
    }

    public function getOriginKindAttribute(): string
    {
        if ($this->linea_correccion_id !== null) {
            return 'corrections';
        }

        return $this->linea_documento_id !== null ? 'inventory-documents' : ($this->linea_entrega_id !== null ? 'deliveries' : ($this->item_prestamo_id !== null ? 'loans' : 'returns'));
    }

    public function getIdentifiersAttribute(): string
    {
        return in_array($this->origin_kind, ['inventory-documents', 'deliveries', 'corrections'], true) ? $this->origen->unidades->pluck('identificador')->join(', ') : $this->origen->unidad->identificador;
    }

    public function correctionLine(): BelongsTo
    {
        return $this->belongsTo(LineaCorreccionInventario::class, 'linea_correccion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function ubicacion(): BelongsTo
    {
        return $this->belongsTo(Ubicacion::class);
    }
}
