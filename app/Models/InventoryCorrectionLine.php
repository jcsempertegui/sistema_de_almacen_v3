<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryCorrectionLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        $guard = function (self $line) {
            if ($line->document()->firstOrFail()->status !== 'draft') {
                throw new \LogicException('Los detalles solicitados o ejecutados se conservan.');
            }
        };
        static::updating($guard);
        static::deleting($guard);
    }

    public function document()
    {
        return $this->belongsTo(InventoryCorrection::class, 'correction_id');
    }

    public function units()
    {
        return $this->belongsToMany(IndividualUnit::class, 'inventory_correction_line_units', 'correction_line_id', 'individual_unit_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
