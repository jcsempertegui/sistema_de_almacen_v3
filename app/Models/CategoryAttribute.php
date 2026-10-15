<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['attribute_id', 'is_required', 'is_identity', 'display_order', 'min_value', 'max_value', 'is_active'])]
class CategoryAttribute extends Model
{
    protected $attributes = ['is_active' => true, 'is_required' => false, 'is_identity' => false, 'display_order' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_required' => 'boolean', 'is_identity' => 'boolean', 'display_order' => 'integer', 'min_value' => 'decimal:6', 'max_value' => 'decimal:6'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CatalogAttribute::class, 'attribute_id');
    }

    public function productAttributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
