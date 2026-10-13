<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;

class WorkerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workers.view');
    }

    public function view(User $user, Worker $worker): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workers.create');
    }

    public function update(User $user, Worker $worker): bool
    {
        return $user->hasPermission('workers.update');
    }

    public function changeStatus(User $user, Worker $worker): bool
    {
        return $user->hasPermission('workers.change_status');
    }
}
