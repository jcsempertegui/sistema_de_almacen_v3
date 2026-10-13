<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Services\SecurityMutation;

class RolePolicy
{
    public function __construct(private SecurityMutation $security) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $this->allows($user, $role, 'roles.update');
    }

    public function changeStatus(User $user, Role $role): bool
    {
        return $this->allows($user, $role, 'roles.change_status');
    }

    public function assignPermissions(User $user, Role $role): bool
    {
        return $this->allows($user, $role, 'roles.assign_permissions');
    }

    private function allows(User $user, Role $role, string $permission): bool
    {
        return $user->hasPermission($permission) && $this->security->canManageRole($user, $role);
    }
}
