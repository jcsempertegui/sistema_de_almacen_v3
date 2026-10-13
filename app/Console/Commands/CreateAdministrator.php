<?php

namespace App\Console\Commands;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AdministrativeAudit;
use App\Services\SecurityMutation;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateAdministrator extends Command
{
    protected $signature = 'sga:create-admin';

    protected $description = 'Crear el primer administrador sin contraseñas predeterminadas';

    public function handle(): int
    {
        if (! Schema::hasTable('permissions')) {
            $this->error('Primero aplica las migraciones del correctivo HU-03.');

            return self::FAILURE;
        }
        $security = app(SecurityMutation::class);
        $role = Role::where('code', Role::ADMINISTRATOR)->first();
        if (! $role || ! $role->is_active || array_diff(PermissionCatalog::ADMINISTRATIVE_ACCESS, $role->assignedPermissionCodes()) !== []) {
            $this->error('Primero aplica las migraciones del Sprint 1.');

            return self::FAILURE;
        }
        if ($security->hasAdministrativeAccess()) {
            $this->error('Ya existe un administrador activo. Gestiona nuevas cuentas desde el sistema.');

            return self::FAILURE;
        }
        $data = [
            'name' => trim((string) $this->ask('Nombre completo')),
            'email' => Str::lower(trim((string) $this->ask('Correo electrónico'))),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número)'),
            'password_confirmation' => $this->secret('Confirma la contraseña'),
        ];
        $request = new UserRequest;
        $validator = Validator::make($data, ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', ...UserRequest::passwordRules()]], $request->messages(), $request->attributes());
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $security->run(null, function () use ($security, $role, $data) {
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            if ($security->hasAdministrativeAccess() || ! $role->is_active || array_diff(PermissionCatalog::ADMINISTRATIVE_ACCESS, $role->assignedPermissionCodes()) !== []) {
                throw ValidationException::withMessages(['email' => 'El acceso administrativo cambió. Revisa las cuentas existentes.']);
            }
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role_id' => $role->id, 'is_active' => true]);
            $audit = app(AdministrativeAudit::class);
            $audit->record(null, 'users.bootstrap_created', $user, null, $audit->userSnapshot($user), 'console');
        });
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
