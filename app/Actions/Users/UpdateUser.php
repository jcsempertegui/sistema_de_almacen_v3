<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\AdministrativeAudit;
use App\Services\SecurityMutation;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function __construct(private SecurityMutation $security, private AdministrativeAudit $audit) {}

    public function handle(User $actor, User $target, array $attributes): User
    {
        return $this->security->run($actor, function (User $operator) use ($target, $attributes) {
            $user = User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->security->requireManageRole($operator, $user->role);
            $before = $this->audit->userSnapshot($user);
            $data = array_intersect_key($attributes, array_flip(['name', 'email', 'password', 'role_id', 'is_active']));
            if (empty($data['password'])) {
                unset($data['password']);
            }
            if (! $operator->hasPermission('users.update') && ! $operator->hasPermission('users.change_status')) {
                $this->security->requirePermissions($operator, 'users.update');
            }
            if (array_key_exists('role_id', $data) && (int) $data['role_id'] !== $user->role_id) {
                $this->security->requirePermissions($operator, 'users.assign_role');
                $newRole = Role::whereKey($data['role_id'])->lockForUpdate()->firstOrFail();
                if (! $newRole->is_active) {
                    throw ValidationException::withMessages(['role_id' => 'Selecciona un rol activo.']);
                }
                $this->security->requireManageRole($operator, $newRole);
            }
            if (isset($data['password'])) {
                $this->security->requirePermissions($operator, 'users.change_password');
            }
            $user->fill($data);
            if ($user->isDirty(['name', 'email'])) {
                $this->security->requirePermissions($operator, 'users.update');
            }
            $statusChanged = $user->isDirty('is_active');
            if ($statusChanged) {
                $this->security->requirePermissions($operator, 'users.change_status');
            }
            $user->unsetRelation('role');
            if ($statusChanged && $user->is_active && ! $user->role->is_active) {
                throw ValidationException::withMessages(['role_id' => 'No puedes activar una cuenta con un rol desactivado.']);
            }
            if (! $user->isDirty()) {
                return $user;
            }
            if ($user->isDirty(['password', 'role_id', 'is_active', 'email'])) {
                $user->session_version++;
            }
            $user->save();
            $after = $this->audit->userSnapshot($user);
            if (isset($data['password'])) {
                $after['password_changed'] = true;
            }
            $this->audit->record($operator, $statusChanged ? 'users.status_changed' : 'users.updated', $user, $before, $after);

            return $user;
        });
    }
}
