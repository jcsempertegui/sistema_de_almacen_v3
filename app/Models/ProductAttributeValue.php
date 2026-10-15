<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['value_text', 'value_decimal', 'value_boolean', 'value_date', 'is_active'])]
class ProductAttributeValue extends Model
{
    use HasFactory;

    public const VALUE_COLUMNS = ['value_text', 'value_decimal', 'value_boolean', 'value_date'];

    protected function casts(): array
    {
        return ['value_decimal' => 'decimal:6', 'value_boolean' => 'boolean', 'value_date' => 'date:Y-m-d', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function categoryAttribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProductAttributeValueRevision::class)->orderBy('revision');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function displayValue(): string
    {
        if ($this->value_boolean !== null) {
            return $this->value_boolean ? 'Sí' : 'No';
        }

        return $this->value_date?->format('Y-m-d') ?? $this->value_decimal ?? $this->value_text ?? '';
    }
}
