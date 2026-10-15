<?php

namespace App\Policies;

use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('products.create');
    }

    public function changeStatus(User $user): bool
    {
        return $user->hasPermission('products.change_status');
    }

    public function view(User $user): bool
    {
        return $user->hasPermission('products.view');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('products.update');
    }
}
