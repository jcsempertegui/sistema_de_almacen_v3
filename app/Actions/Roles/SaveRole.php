<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\AdministrativeAudit;
use App\Services\SecurityMutation;
use App\Support\PermissionCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class SaveRole
{
    public function __construct(private SecurityMutation $security, private AdministrativeAudit $audit) {}

    public function handle(User $actor, ?Role $target, array $data): Role
    {
        return $this->security->run($actor, function (User $operator) use ($target, $data) {
            $creating = $target === null;
            $role = $creating ? new Role : Role::whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($creating) {
                $this->security->requirePermissions($operator, 'roles.create');
            } else {
                if (! $operator->hasPermission('roles.update') && ! $operator->hasPermission('roles.assign_permissions') && ! $operator->hasPermission('roles.change_status')) {
                    throw new AuthorizationException('No tienes permiso para modificar roles.');
                }
                $this->security->requireManageRole($operator, $role);
            }
            $before = $creating ? null : $this->audit->roleSnapshot($role);
            $attributes = array_intersect_key($data, array_flip($creating ? ['code', 'name', 'description', 'is_active'] : ['name', 'description', 'is_active']));
            $role->fill($attributes);
            if (! $creating && $role->isDirty(['name', 'description'])) {
                $this->security->requirePermissions($operator, 'roles.update');
            }
            $statusChanged = ! $creating && $role->isDirty('is_active');
            if ($statusChanged) {
                $this->security->requirePermissions($operator, 'roles.change_status');
            }
            $oldCodes = $before['permission_codes'] ?? [];
            $codes = $oldCodes;
            $selected = null;
            if (array_key_exists('permissions', $data)) {
                $ids = array_map('intval', $data['permissions']);
                $selected = Permission::whereIn('id', $ids)->whereIn('code', array_keys(PermissionCatalog::LABELS))->orderBy('code')->get();
                if ($selected->count() !== count(array_unique($ids))) {
                    throw ValidationException::withMessages(['permissions' => 'Selecciona permisos válidos.']);
                }
                $codes = $selected->pluck('code')->all();
            }
            $permissionsChanged = $codes !== $oldCodes;
            if ($permissionsChanged) {
                $this->security->requirePermissions($operator, 'roles.assign_permissions');
                $this->security->requireGrant($operator, $codes);
            }
            if (! $creating && ! $role->isDirty() && ! $permissionsChanged) {
                return $role;
            }
            if ($statusChanged || (! $creating && $permissionsChanged)) {
                $role->permissions_version++;
            }
            $role->updated_by = $operator->id;
            if ($creating) {
                $role->created_by = $operator->id;
            }
            $role->save();
            if ($permissionsChanged) {
                $newIds = $selected->pluck('id')->all();
                foreach ($role->permissions()->wherePivot('is_active', true)->pluck('permissions.id') as $id) {
                    if (! in_array($id, $newIds, true)) {
                        $role->permissions()->updateExistingPivot($id, ['is_active' => false]);
                    }
                }
                foreach ($newIds as $id) {
                    $role->permissions()->syncWithoutDetaching([$id => ['is_active' => true]]);
                }
            }
            $after = $this->audit->roleSnapshot($role);
            $after['granted_permissions'] = array_values(array_diff($codes, $oldCodes));
            $after['revoked_permissions'] = array_values(array_diff($oldCodes, $codes));
            $action = $creating ? 'roles.created' : ($permissionsChanged ? 'roles.permissions_changed' : ($statusChanged ? 'roles.status_changed' : 'roles.updated'));
            $this->audit->record($operator, $action, $role, $before, $after);

            return $role;
        });
    }
}
