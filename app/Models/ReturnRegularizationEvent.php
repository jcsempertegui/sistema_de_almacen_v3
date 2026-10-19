<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnRegularizationEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('El historial no se modifica.'));
        static::deleting(fn () => throw new \LogicException('El historial no se elimina.'));
    }
}
