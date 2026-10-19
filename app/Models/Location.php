<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'type', 'parent_id', 'is_storable', 'description', 'is_active'])]
class Location extends Model
{
    use HasFactory;

    public const TYPES = ['sector' => 'Sector', 'shelf' => 'Estante', 'cabinet' => 'Gabinete'];

    protected $attributes = ['is_active' => true, 'is_storable' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_storable' => 'boolean', 'parent_id' => 'integer'];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function productLocations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function individualUnits(): HasMany
    {
        return $this->hasMany(IndividualUnit::class);
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
