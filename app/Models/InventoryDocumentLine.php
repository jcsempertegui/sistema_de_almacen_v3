<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class InventoryDocumentLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        $draftOnly = function (self $line) {
            $document = InventoryDocument::whereKey($line->document_id)->lockForUpdate()->firstOrFail();
            if ($document->status !== 'draft') {
                throw new LogicException('Las líneas de documentos cerrados se conservan sin modificaciones.');
            }
        };
        static::creating($draftOnly);
        static::updating($draftOnly);
        static::deleting($draftOnly);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(InventoryDocument::class, 'document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(IndividualUnit::class, 'inventory_document_line_units', 'document_line_id', 'individual_unit_id');
    }
}
