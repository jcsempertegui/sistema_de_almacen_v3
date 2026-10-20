<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        if (Schema::hasTable('document_compensations') && DB::table('document_compensations')->where('loan_id', $this->id)->exists()) {
            return 'compensated';
        }
        $closed = $total - $this->lines()->pending()->count();

        return $closed === $total ? 'returned' : ($closed > 0 ? 'partial' : 'pending');
    }
}
