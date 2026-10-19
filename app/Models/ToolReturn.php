<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ToolReturn extends WorkerOperationDocument
{
    protected $prefix = 'DV';

    public function lines(): HasMany
    {
        return $this->hasMany(ToolReturnItem::class, 'return_id')->orderBy('id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(ToolLoan::class, 'loan_id');
    }
}
