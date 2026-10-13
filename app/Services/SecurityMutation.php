<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SecurityMutation
{
    public function run(?User $actor, Closure $operation, bool $protectAdministrativeAccess = true): mixed
    {
        return DB::transaction(function () use ($actor, $operation, $protectAdministrativeAccess) {
            // This immutable catalog row serializes ALL administrative mutations,
            // including first-account creation, user status and role capabilities.
            Permission::where('code', 'roles.assign_permissions')->lockForUpdate()->firstOrFail();
            $current = null;
            if ($actor) {
                $expectedRole = $actor->role;
                $current = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $current->load('role');
                if (! $current->is_active || ! $current->role?->is_active
                    || $actor->session_version !== $current->session_version
                    || $actor->role_id !== $current->role_id
                    || $expectedRole?->permissions_version !== $current->role->permissions_version) {
                    throw new AuthorizationException('El acceso del operador cambió. Inicia sesión nuevamente.');
                }
            }
            $result = $operation($current);
            if ($protectAdministrativeAccess && ! $this->hasAdministrativeAccess()) {
                throw ValidationException::withMessages(['is_active' => 'Debe permanecer una cuenta activa con capacidad completa para gestionar usuarios, roles y permisos.']);
            }

            return $result;
        }, attempts: 3);
    }

    public function hasAdministrativeAccess(): bool
    {
        return User::where('is_active', true)->whereHas('role', function ($roles) {
            $roles->where('is_active', true)->whereHas('permissions', function ($permissions) {
                $permissions->whereIn('permissions.code', PermissionCatalog::ADMINISTRATIVE_ACCESS)->where('permission_role.is_active', true);
            }, '=', count(PermissionCatalog::ADMINISTRATIVE_ACCESS));
        })->exists();
    }

    public function requirePermissions(User $actor, string ...$codes): void
    {
        if (! $actor->hasAllPermissions($codes)) {
            throw new AuthorizationException('No tienes permiso para realizar esta operación.');
        }
    }

    public function canManageRole(User $actor, Role $role): bool
    {
        return array_diff($role->assignedPermissionCodes(), $actor->permissionCodes()) === [];
    }

    public function canManageUser(User $actor, User $target): bool
    {
        return $target->role && $this->canManageRole($actor, $target->role);
    }

    public function requireManageRole(User $actor, Role $role): void
    {
        if (! $this->canManageRole($actor, $role)) {
            throw new AuthorizationException('No puedes modificar un rol o cuenta con capacidades superiores a las tuyas.');
        }
    }

    public function requireGrant(User $actor, array $codes): void
    {
        if (array_diff($codes, $actor->permissionCodes()) !== []) {
            throw new AuthorizationException('No puedes conceder permisos que no posees.');
        }
    }
}
