<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class Category extends Model
{
    use HasFactory;

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = Str::upper(trim($value));
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = trim($value);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryAttributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
