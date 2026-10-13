<?php

namespace App\Policies;

use App\Models\User;
use App\Services\SecurityMutation;

class UserPolicy
{
    public function __construct(private SecurityMutation $security) {}

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->hasAllPermissions(['users.create', 'users.assign_role']);
    }

    public function update(User $actor, User $target): bool
    {
        return $this->allows($actor, $target, 'users.update');
    }

    public function changeStatus(User $actor, User $target): bool
    {
        return $this->allows($actor, $target, 'users.change_status');
    }

    public function assignRole(User $actor, User $target): bool
    {
        return $this->allows($actor, $target, 'users.assign_role');
    }

    public function changePassword(User $actor, User $target): bool
    {
        return $this->allows($actor, $target, 'users.change_password');
    }

    private function allows(User $actor, User $target, string $permission): bool
    {
        return $actor->hasPermission($permission) && $this->security->canManageUser($actor, $target);
    }
}
