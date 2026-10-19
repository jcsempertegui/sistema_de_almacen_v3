<?php

namespace App\Policies;

use App\Models\User;

class IndividualUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('individual_units.view');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('individual_units.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('individual_units.create');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('individual_units.update');
    }

    public function changeStatus(User $user): bool
    {
        return $user->hasPermission('individual_units.change_status');
    }
}
