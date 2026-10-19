<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ToolReturnItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $m) {
            if (ToolReturn::findOrFail($m->return_id)->status !== 'draft') {
                throw new LogicException('Los ítems confirmados se conservan.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(ToolReturn::class, 'return_id');
    }

    public function loanItem(): BelongsTo
    {
        return $this->belongsTo(ToolLoanItem::class, 'loan_item_id');
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

    public function authorizations(): HasMany
    {
        return $this->hasMany(ReturnRegularizationAuthorization::class, 'return_item_id');
    }
}
