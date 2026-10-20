<?php

namespace App\Models;

use App\Models\Concerns\HasCompensation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryCorrection extends Model
{
    use HasCompensation;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['document_date' => 'date', 'executed_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryCorrectionLine::class, 'correction_id')->orderBy('id');
    }

    public function authorizations(): HasMany
    {
        return $this->hasMany(CorrectionAuthorization::class, 'correction_id')->orderByDesc('id');
    }

    protected static function booted(): void
    {
        static::updating(function (self $m) {
            if ($m->getRawOriginal('status') === 'executed') {
                throw new \LogicException('La corrección ejecutada se conserva.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Las correcciones no se eliminan.'));
    }

    public function getCodeAttribute(): string
    {
        return 'RG-CO-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }
}
