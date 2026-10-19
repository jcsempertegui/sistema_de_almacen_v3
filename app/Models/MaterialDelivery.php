<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialDelivery extends WorkerOperationDocument
{
    protected $prefix = 'EN';

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialDeliveryLine::class, 'delivery_id')->orderBy('line_number');
    }
}
