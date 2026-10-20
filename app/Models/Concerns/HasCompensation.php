<?php

namespace App\Models\Concerns;

use App\Models\InventoryCorrection;
use App\Models\InventoryDocument;
use App\Models\MaterialDelivery;
use App\Models\ToolLoan;
use App\Models\ToolReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait HasCompensation
{
    public function getCompensationIdAttribute(): ?int
    {
        if (! Schema::hasTable('document_compensations')) {
            return null;
        }
        $column = match (static::class) {
            InventoryDocument::class => 'inventory_document_id',MaterialDelivery::class => 'delivery_id',ToolLoan::class => 'loan_id',ToolReturn::class => 'return_id',InventoryCorrection::class => 'target_correction_id',default => null
        };

        return $column ? DB::table('document_compensations')->where($column, $this->id)->value('correction_id') : null;
    }
}
