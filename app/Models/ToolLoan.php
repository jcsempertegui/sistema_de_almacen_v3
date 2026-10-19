<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ToolLoan extends WorkerOperationDocument
{
    protected $prefix = 'PR';

    public function lines(): HasMany
    {
        return $this->hasMany(ToolLoanItem::class, 'loan_id')->orderBy('id');
    }

    public function getResponsibilityStatusAttribute(): string
    {
        if ($this->status !== 'confirmed') {
            return $this->status;
        }
        $total = $this->lines()->count();
        $closed = DB::table('loan_item_settlements')->whereIn('loan_item_id', $this->lines()->select('id'))->count();

        return $closed === $total ? 'returned' : ($closed > 0 ? 'partial' : 'pending');
    }
}
