<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.update');
    }

    public function changeStatus(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.change_status');
    }
}
