<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'descripcion', 'activo'])]
class Rol extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'roles';

    protected $attributes = ['activo' => true, 'version_permisos' => 1];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'version_permisos' => 'integer'];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class)->withPivot('activo')->withTimestamps();
    }

    public function codigosPermisosAsignados(): array
    {
        $query = $this->permisos()->wherePivot('activo', true)->orderBy('codigo');
        // Mutations must not authorize from an older REPEATABLE-READ snapshot.
        if ($this->getConnection()->transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        return $query->pluck('codigo')->all();
    }

    public const ADMINISTRATOR = 'administrador';

    public const WAREHOUSE_MANAGER = 'encargado';

    public const AUDITOR = 'consulta_auditor';

    public const LABELS = [
        self::ADMINISTRATOR => 'Administrador',
        self::WAREHOUSE_MANAGER => 'Encargado de almacén',
        self::AUDITOR => 'Consulta/Auditor',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
