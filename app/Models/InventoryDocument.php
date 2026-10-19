<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class InventoryDocument extends Model
{
    public const DISPLAY_TIMEZONE = 'America/La_Paz';

    public const KINDS = ['receipt' => 'Ingreso', 'issue' => 'Egreso', 'opening' => 'Apertura'];

    public const STATES = ['draft' => 'Borrador', 'confirmed' => 'Confirmado', 'cancelled' => 'Cancelado'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['lock_version' => 'integer', 'document_date' => 'date', 'cutoff_date' => 'date', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $document) {
            if ($document->getRawOriginal('status') !== 'draft') {
                throw new LogicException('El documento cerrado es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los documentos se conservan. Cancela el borrador.'));
    }

    public function permission(string $operation): string
    {
        if ($operation === 'confirm') {
            return $this->kind === 'opening' ? 'initial_stock.confirm' : 'remittances.confirm_'.$this->kind;
        }

        return ($this->kind === 'opening' ? 'initial_stock.' : 'remittances.').$operation;
    }

    public function getCodeAttribute(): string
    {
        return 'RG-'.(['receipt' => 'IN', 'issue' => 'EG', 'opening' => 'AP'][$this->kind]).'-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryDocumentLine::class, 'document_id')->orderBy('line_number');
    }
}
