<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(...array_keys(Role::LABELS));
    }

    public function view(User $user, Worker $worker): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::ADMINISTRATOR, Role::WAREHOUSE_MANAGER);
    }

    public function update(User $user, Worker $worker): bool
    {
        return $this->create($user);
    }
}
