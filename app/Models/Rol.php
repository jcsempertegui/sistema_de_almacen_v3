<?php

namespace App\Models;

use Closure;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['codigo', 'nombre', 'descripcion', 'activo'])]
class Rol extends Model
{
    private ?array $permisosConsulta = null;

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
        if ($this->permisosConsulta !== null
            && $this->permisosConsulta['nivel'] === $this->getConnection()->transactionLevel()
            && $this->permisosConsulta['version'] === $this->version_permisos) {
            return $this->permisosConsulta['codigos'];
        }

        return $this->consultarCodigosPermisos();
    }

    /** Solo durante una respuesta GET/HEAD; no persiste entre peticiones. */
    public function duranteConsulta(Closure $operacion): mixed
    {
        $anterior = $this->permisosConsulta;
        $this->permisosConsulta = [
            'nivel' => $this->getConnection()->transactionLevel(),
            'version' => $this->version_permisos,
            'codigos' => $this->consultarCodigosPermisos(),
        ];
        try {
            return $operacion();
        } finally {
            $this->permisosConsulta = $anterior;
        }
    }

    private function consultarCodigosPermisos(): array
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
