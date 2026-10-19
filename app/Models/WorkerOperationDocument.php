<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

abstract class WorkerOperationDocument extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['worker_snapshot' => 'array', 'document_date' => 'date', 'due_date' => 'date', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $m) {
            if ($m->getRawOriginal('status') !== 'draft') {
                throw new LogicException('El documento confirmado o cancelado es inmutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Los documentos se conservan. Cancela el borrador.'));
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getCodeAttribute(): string
    {
        return 'RG-'.$this->prefix.'-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }
}
