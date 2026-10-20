<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApprovalAccess
{
    public function proof(User $u): array
    {
        return ['user_id' => $u->id, 'role_id' => $u->role_id, 'role_version' => $u->role->permissions_version, 'session_version' => $u->session_version];
    }

    public function valid(int $id, string $code, array $proof): bool
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('La aprobación requiere lecturas actuales dentro de una transacción.');
        }
        $user = User::whereKey($id)->lockForUpdate()->first();
        if (! $user?->is_active) {
            return false;
        }$role = Role::whereKey($user->role_id)->lockForUpdate()->first();
        if (! $role?->is_active) {
            return false;
        }$user->setRelation('role', $role);

        return $proof === $this->proof($user) && $user->hasPermission($code);
    }
}
