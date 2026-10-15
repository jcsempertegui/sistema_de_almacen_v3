<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'data_type', 'measurement_unit_id', 'help_text', 'is_active'])]
class CatalogAttribute extends Model
{
    protected $table = 'attributes';

    protected $attributes = ['is_active' => true];

    public const TYPES = ['text' => 'Texto', 'decimal' => 'Decimal', 'boolean' => 'Booleano', 'date' => 'Fecha'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'measurement_unit_id' => 'integer'];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(MeasurementUnit::class);
    }

    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class, 'attribute_id');
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
