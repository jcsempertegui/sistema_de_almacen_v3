<?php

namespace App\Policies;

use App\Models\CategoryAttribute;
use App\Models\User;

class CategoryAttributePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('category_attributes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('category_attributes.create');
    }

    public function update(User $user, CategoryAttribute $target): bool
    {
        return $user->hasPermission('category_attributes.update');
    }

    public function changeStatus(User $user, CategoryAttribute $target): bool
    {
        return $user->hasPermission('category_attributes.change_status');
    }
}
