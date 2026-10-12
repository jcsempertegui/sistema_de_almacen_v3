<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function handle(User $actor, User $target, array $attributes): User
    {
        return DB::transaction(function () use ($actor, $target, $attributes) {
            $adminRoleId = Role::where('code', Role::ADMINISTRATOR)->value('id');
            // All account changes acquire the same locks in ID order. Concurrent
            // demotions cannot both see the other administrator as available.
            $admins = User::where('role_id', $adminRoleId)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
            $currentActor = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $user = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($currentActor)->authorize('update', $user);
            if ($currentActor->session_version !== $actor->session_version) {
                throw new AuthorizationException('El acceso del operador cambió durante la solicitud.');
            }
            $wasActiveAdmin = $user->is_active && $user->role_id === $adminRoleId;
            $newRoleId = (int) ($attributes['role_id'] ?? $user->role_id);
            $newActive = (bool) ($attributes['is_active'] ?? $user->is_active);
            if ($wasActiveAdmin && (! $newActive || $newRoleId !== $adminRoleId) && $admins->count() <= 1) {
                throw ValidationException::withMessages(['is_active' => 'Debe permanecer al menos un administrador activo.']);
            }
            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }
            $user->fill($attributes);
            if ($user->isDirty(['password', 'role_id', 'is_active', 'email'])) {
                $user->session_version++;
            }
            $user->save();

            return $user;
        }, attempts: 3);
    }
}
