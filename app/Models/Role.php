<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'is_active'])]
class Role extends Model
{
    protected $attributes = ['is_active' => true, 'permissions_version' => 1];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'permissions_version' => 'integer'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)->withPivot('is_active')->withTimestamps();
    }

    public function assignedPermissionCodes(): array
    {
        $query = $this->permissions()->wherePivot('is_active', true)->orderBy('code');
        // Mutations must not authorize from an older REPEATABLE-READ snapshot.
        if ($this->getConnection()->transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->pluck('code')->all();
    }

    public const ADMINISTRATOR = 'administrador';

    public const WAREHOUSE_MANAGER = 'encargado';

    public const AUDITOR = 'consulta_auditor';

    public const LABELS = [
        self::ADMINISTRATOR => 'Administrador',
        self::WAREHOUSE_MANAGER => 'Encargado de almacén',
        self::AUDITOR => 'Consulta/Auditor',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
