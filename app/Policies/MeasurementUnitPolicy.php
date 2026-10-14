<?php

namespace App\Policies;

use App\Models\MeasurementUnit;
use App\Models\User;

class MeasurementUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('measurement_units.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('measurement_units.create');
    }

    public function update(User $user, MeasurementUnit $unit): bool
    {
        return $user->hasPermission('measurement_units.update');
    }

    public function changeStatus(User $user, MeasurementUnit $unit): bool
    {
        return $user->hasPermission('measurement_units.change_status');
    }
}
