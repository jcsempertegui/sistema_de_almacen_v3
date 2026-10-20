<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class ToolLoanItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $m) {
            if (ToolLoan::findOrFail($m->loan_id)->status !== 'draft') {
                throw new LogicException('Los ítems confirmados se conservan.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ToolLoan::class, 'loan_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(IndividualUnit::class, 'individual_unit_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function settlement(): HasOne
    {
        return $this->hasOne(LoanItemSettlement::class, 'loan_item_id');
    }

    public function scopePending($query)
    {
        return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('individual_unit_custodies')->whereColumn('individual_unit_custodies.current_loan_item_id', 'tool_loan_items.id'));
    }
}
