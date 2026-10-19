<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;

class MaterialDeliveryLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $m) {
            if (MaterialDelivery::findOrFail($m->delivery_id)->status !== 'draft') {
                throw new LogicException('Las líneas confirmadas se conservan.');
            }
        };
        static::saving($guard);
        static::deleting($guard);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(MaterialDelivery::class, 'delivery_id');
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
        return $this->belongsToMany(IndividualUnit::class, 'material_delivery_line_units', 'delivery_line_id', 'individual_unit_id');
    }
}
