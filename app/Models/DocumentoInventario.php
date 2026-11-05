<?php

namespace App\Models;

use App\Models\Concerns\TieneCompensacion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class DocumentoInventario extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'documentos_inventario';

    use TieneCompensacion;

    public const DISPLAY_TIMEZONE = 'America/La_Paz';

    public const KINDS = ['receipt' => 'Ingreso', 'issue' => 'Egreso', 'opening' => 'Apertura'];

    public const STATES = ['draft' => 'Borrador', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['version_bloqueo' => 'integer', 'fecha_documento' => 'date', 'fecha_corte' => 'date', 'fecha_confirmacion' => 'datetime', 'fecha_cancelacion' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $documento) {
            if ($documento->getRawOriginal('estado') !== 'draft') {
                throw new LogicException('El documento cerrado es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los documentos se conservan. Cancela el borrador.'));
    }

    public function permiso(string $operation): string
    {
        if ($operation === 'confirm') {
            return $this->tipo === 'opening' ? 'initial_stock.confirm' : 'remittances.confirm_'.$this->tipo;
        }

        return ($this->tipo === 'opening' ? 'initial_stock.' : 'remittances.').$operation;
    }

    public function getCodigoAttribute(): string
    {
        return 'RG-'.(['receipt' => 'IN', 'issue' => 'EG', 'opening' => 'AP'][$this->tipo]).'-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(LineaDocumentoInventario::class, 'documento_id')->orderBy('numero_linea');
    }
}
