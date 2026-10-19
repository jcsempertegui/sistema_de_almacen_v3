<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRegularizationAuthorization extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Utiliza el procedimiento administrativo autorizado.'));
        static::deleting(fn () => throw new \LogicException('Las autorizaciones se conservan.'));
    }

    protected function casts(): array
    {
        return ['reference_snapshot' => 'array', 'expires_at' => 'datetime', 'approved_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ToolReturnItem::class, 'return_item_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReturnRegularizationEvent::class, 'authorization_id')->orderBy('id');
    }
}
