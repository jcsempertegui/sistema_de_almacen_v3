<?php

namespace App\Policies;

use App\Models\User;

class AdministrativeAuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('audit.view');
    }
}
