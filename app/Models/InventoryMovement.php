<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class InventoryMovement extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los movimientos confirmados son inmutables.'));
        static::deleting(fn () => throw new LogicException('Los movimientos confirmados no se eliminan.'));
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(InventoryDocumentLine::class, 'document_line_id');
    }

    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(MaterialDeliveryLine::class, 'delivery_line_id');
    }

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(ToolLoanItem::class, 'loan_item_id');
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ToolReturnItem::class, 'return_item_id');
    }

    public function getOriginAttribute(): InventoryDocumentLine|MaterialDeliveryLine|ToolLoanItem|ToolReturnItem|InventoryCorrectionLine
    {
        if ($this->correction_line_id !== null) {
            return $this->correctionLine;
        }

        return $this->document_line_id !== null ? $this->line : ($this->delivery_line_id !== null ? $this->deliveryLine : ($this->loan_item_id !== null ? $this->loanItem : $this->returnItem));
    }

    public function getOriginKindAttribute(): string
    {
        if ($this->correction_line_id !== null) {
            return 'corrections';
        }

        return $this->document_line_id !== null ? 'inventory-documents' : ($this->delivery_line_id !== null ? 'deliveries' : ($this->loan_item_id !== null ? 'loans' : 'returns'));
    }

    public function getIdentifiersAttribute(): string
    {
        return in_array($this->origin_kind, ['inventory-documents', 'deliveries', 'corrections'], true) ? $this->origin->units->pluck('identifier')->join(', ') : $this->origin->unit->identifier;
    }

    public function correctionLine(): BelongsTo
    {
        return $this->belongsTo(InventoryCorrectionLine::class, 'correction_line_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
