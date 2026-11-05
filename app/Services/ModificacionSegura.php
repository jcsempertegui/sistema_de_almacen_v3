<?php

namespace App\Services;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\CatalogoPermisos;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModificacionSegura
{
    public function run(?Usuario $actor, Closure $operation, bool $protectAdministrativeAccess = true): mixed
    {
        return DB::transaction(function () use ($actor, $operation, $protectAdministrativeAccess) {
            // This immutable catalog row serializes ALL administrative mutations,
            // including first-account creation, usuario estado and rol capabilities.
            Permiso::where('codigo', 'roles.assign_permissions')->lockForUpdate()->firstOrFail();
            $current = null;
            if ($actor) {
                $expectedRole = $actor->rol;
                $current = Usuario::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $current->setRelation('rol', Rol::whereKey($current->rol_id)->lockForUpdate()->first());
                if (! $current->activo || ! $current->rol?->activo
                    || $actor->version_sesion !== $current->version_sesion
                    || $actor->rol_id !== $current->rol_id
                    || $expectedRole?->version_permisos !== $current->rol->version_permisos) {
                    throw new AuthorizationException('El acceso del operador cambió. Inicia sesión nuevamente.');
                }
            }
            $resultado = $operation($current);
            if ($protectAdministrativeAccess && ! $this->hasAdministrativeAccess()) {
                throw ValidationException::withMessages(['activo' => 'Debe permanecer una cuenta activa con capacidad completa para gestionar usuarios, roles y permisos.']);
            }

            return $resultado;
        }, attempts: 3);
    }

    public function hasAdministrativeAccess(): bool
    {
        return Usuario::where('activo', true)->whereHas('rol', function ($roles) {
            $roles->where('activo', true)->whereHas('permisos', function ($permisos) {
                $permisos->whereIn('permisos.codigo', CatalogoPermisos::ADMINISTRATIVE_ACCESS)->where('permiso_rol.activo', true);
            }, '=', count(CatalogoPermisos::ADMINISTRATIVE_ACCESS));
        })->exists();
    }

    public function exigirPermisos(Usuario $actor, string ...$codes): void
    {
        if (! $actor->tieneTodosPermisos($codes)) {
            throw new AuthorizationException('No tienes permiso para realizar esta operación.');
        }
    }

    public function canManageRole(Usuario $actor, Rol $rol): bool
    {
        return array_diff($rol->codigosPermisosAsignados(), $actor->codigosPermisos()) === [];
    }

    public function canManageUser(Usuario $actor, Usuario $target): bool
    {
        return $target->rol && $this->canManageRole($actor, $target->rol);
    }

    public function exigirGestionRol(Usuario $actor, Rol $rol): void
    {
        if (! $this->canManageRole($actor, $rol)) {
            throw new AuthorizationException('No puedes modificar un rol o cuenta con capacidades superiores a las tuyas.');
        }
    }

    public function requireGrant(Usuario $actor, array $codes): void
    {
        if (array_diff($codes, $actor->codigosPermisos()) !== []) {
            throw new AuthorizationException('No puedes conceder permisos que no posees.');
        }
    }
}
