<?php

namespace App\Console\Commands;

use App\Http\Requests\UserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdministrator extends Command
{
    protected $signature = 'sga:create-admin';

    protected $description = 'Crear el primer administrador sin contraseñas predeterminadas';

    public function handle(): int
    {
        $role = Role::where('code', Role::ADMINISTRATOR)->first();
        if (! $role) {
            $this->error('Primero aplica las migraciones del Sprint 1.');

            return self::FAILURE;
        }
        if (User::where('role_id', $role->id)->where('is_active', true)->exists()) {
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
        $created = DB::transaction(function () use ($role, $data) {
            // Lock the fixed catalog row to serialize bootstrap commands.
            Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            if (User::where('role_id', $role->id)->where('is_active', true)->exists()) {
                return false;
            }
            User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role_id' => $role->id, 'is_active' => true]);

            return true;
        }, attempts: 3);
        if (! $created) {
            $this->error('Otro proceso ya creó un administrador.');

            return self::FAILURE;
        }
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
