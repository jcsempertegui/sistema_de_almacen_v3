<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndividualUnitPosition extends Model
{
    protected $primaryKey = 'individual_unit_id';

    public $incrementing = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['is_present' => 'boolean', 'lock_version' => 'integer'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(IndividualUnit::class, 'individual_unit_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lastLine(): BelongsTo
    {
        return $this->belongsTo(InventoryDocumentLine::class, 'last_document_line_id');
    }
}
