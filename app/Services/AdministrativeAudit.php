<?php

namespace App\Services;

use App\Models\AdministrativeAuditLog;
use App\Models\Role;
use App\Models\User;

class AdministrativeAudit
{
    public function userSnapshot(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'role_id' => $user->role_id, 'is_active' => $user->is_active];
    }

    public function roleSnapshot(Role $role): array
    {
        return ['code' => $role->code, 'name' => $role->name, 'description' => $role->description, 'is_active' => $role->is_active, 'permissions_version' => $role->permissions_version, 'permission_codes' => $role->assignedPermissionCodes()];
    }

    public function record(?User $actor, string $action, User|Role $subject, ?array $before, array $after, string $origin = 'web'): void
    {
        AdministrativeAuditLog::create([
            'actor_id' => $actor?->id, 'actor_name' => $actor?->name,
            'user_id' => $subject instanceof User ? $subject->id : null,
            'role_id' => $subject instanceof Role ? $subject->id : null,
            'action' => $action, 'origin' => $origin,
            'ip_address' => $origin === 'web' ? request()->ip() : null,
            'before_data' => $before, 'after_data' => $after, 'created_at' => now(),
        ]);
    }
}
