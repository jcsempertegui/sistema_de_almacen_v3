<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanItemSettlement extends Model
{
    protected $primaryKey = 'loan_item_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El cierre confirmado se conserva.'));
        static::deleting(fn () => throw new \LogicException('El cierre confirmado se conserva.'));
    }

    public function returnItem(): BelongsTo
    {
        return $this->belongsTo(ToolReturnItem::class, 'return_item_id');
    }
}
