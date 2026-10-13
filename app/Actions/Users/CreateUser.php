<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use App\Services\AdministrativeAudit;
use App\Services\SecurityMutation;
use Illuminate\Validation\ValidationException;

class CreateUser
{
    public function __construct(private SecurityMutation $security, private AdministrativeAudit $audit) {}

    public function handle(User $actor, array $data): User
    {
        return $this->security->run($actor, function (User $operator) use ($data) {
            $this->security->requirePermissions($operator, 'users.create', 'users.assign_role');
            $role = Role::whereKey($data['role_id'])->lockForUpdate()->firstOrFail();
            if (! $role->is_active) {
                throw ValidationException::withMessages(['role_id' => 'Selecciona un rol activo.']);
            }
            $this->security->requireManageRole($operator, $role);
            $user = User::create(array_intersect_key($data, array_flip(['name', 'email', 'password', 'role_id', 'is_active'])));
            $user->refresh();
            $this->audit->record($operator, 'users.created', $user, null, $this->audit->userSnapshot($user));

            return $user;
        });
    }
}
