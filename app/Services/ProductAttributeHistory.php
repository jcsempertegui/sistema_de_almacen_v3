<?php

namespace App\Services;

use App\Models\ProductAttributeValue;
use App\Models\ProductAttributeValueRevision;
use App\Models\User;

class ProductAttributeHistory
{
    // Called only while holding the aggregate lock inside SecurityMutation.
    public function record(ProductAttributeValue $value, User $actor): void
    {
        $snapshot = array_intersect_key($value->getAttributes(), array_flip([...ProductAttributeValue::VALUE_COLUMNS, 'is_active']));
        ProductAttributeValueRevision::create($snapshot + [
            'product_attribute_value_id' => $value->id,
            'revision' => ($value->revisions()->max('revision') ?? 0) + 1,
            'created_by' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
