<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('locations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('locations.create');
    }

    public function update(User $user, Location $location): bool
    {
        return $user->hasPermission('locations.update');
    }

    public function changeStatus(User $user, Location $location): bool
    {
        return $user->hasPermission('locations.change_status');
    }
}
