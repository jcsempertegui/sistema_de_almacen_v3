<?php

namespace App\Policies;

use App\Models\CatalogAttribute;
use App\Models\User;

class CatalogAttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attributes.create');
    }

    public function update(User $user, CatalogAttribute $target): bool
    {
        return $user->hasPermission('attributes.update');
    }

    public function changeStatus(User $user, CatalogAttribute $target): bool
    {
        return $user->hasPermission('attributes.change_status');
    }
}
