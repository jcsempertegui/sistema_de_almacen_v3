<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'symbol', 'dimension', 'decimal_places', 'is_active'])]
class MeasurementUnit extends Model
{
    use HasFactory;

    public const DIMENSIONS = ['count' => 'Conteo', 'length' => 'Longitud', 'mass' => 'Masa', 'volume' => 'Volumen', 'time' => 'Tiempo', 'other' => 'Otra'];

    protected $attributes = ['is_active' => true, 'decimal_places' => 0];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'decimal_places' => 'integer'];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function setSymbolAttribute(string $value): void
    {
        $this->attributes['symbol'] = trim($value);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(CatalogAttribute::class, 'measurement_unit_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
