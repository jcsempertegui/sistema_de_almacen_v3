<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['internal_code', 'name', 'description', 'category_id', 'measurement_unit_id', 'brand', 'manufacturer_reference', 'control_mode', 'is_loanable', 'is_active'])]
class Product extends Model
{
    use HasFactory;

    public const MODES = ['quantity' => 'Por cantidad', 'individual' => 'Individual'];

    protected $attributes = ['is_active' => true, 'is_loanable' => false, 'lock_version' => 1];

    public function setInternalCodeAttribute(string $value): void
    {
        $this->attributes['internal_code'] = Str::upper(trim($value));
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_loanable' => 'boolean', 'lock_version' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function productLocations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'product_locations')->withPivot(['id', 'is_active', 'created_by', 'updated_by'])->withTimestamps();
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
