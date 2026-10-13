<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['actor_id', 'actor_name', 'role_id', 'user_id', 'action', 'origin', 'ip_address', 'before_data', 'after_data', 'created_at'])]
class AdministrativeAuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['before_data' => 'array', 'after_data' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('La auditoría no admite modificaciones.'));
        static::deleting(fn () => throw new \LogicException('La auditoría no admite eliminaciones.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
