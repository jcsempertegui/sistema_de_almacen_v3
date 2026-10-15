<?php

namespace App\Policies;

use App\Models\User;

class ProductLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('product_locations.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('product_locations.create');
    }

    public function changeStatus(User $user): bool
    {
        return $user->hasPermission('product_locations.change_status');
    }
}
