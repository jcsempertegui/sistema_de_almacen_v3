<?php

namespace App\Console\Commands;

use App\Http\Requests\SolicitudUsuario;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaAdministrativa;
use App\Services\ModificacionSegura;
use App\Support\CatalogoPermisos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrearAdministrador extends Command
{
    protected $signature = 'sga:create-admin';

    protected $description = 'Crear el primer administrador sin contraseñas predeterminadas';

    public function handle(): int
    {
        if (! Schema::hasTable('permisos')) {
            $this->error('Primero aplica las migraciones del correctivo HU-03.');

            return self::FAILURE;
        }
        $security = app(ModificacionSegura::class);
        $rol = Rol::where('codigo', Rol::ADMINISTRATOR)->first();
        if (! $rol || ! $rol->activo || array_diff(CatalogoPermisos::ADMINISTRATIVE_ACCESS, $rol->codigosPermisosAsignados()) !== []) {
            $this->error('Primero aplica las migraciones del Sprint 1.');

            return self::FAILURE;
        }
        if ($security->hasAdministrativeAccess()) {
            $this->error('Ya existe un administrador activo. Gestiona nuevas cuentas desde el sistema.');

            return self::FAILURE;
        }
        $data = [
            'nombre' => trim((string) $this->ask('Nombre completo')),
            'correo' => Str::lower(trim((string) $this->ask('Correo electrónico'))),
            'contrasena' => $this->secret('Contraseña (mínimo 12 caracteres, mayúscula, minúscula y número)'),
            'contrasena_confirmation' => $this->secret('Confirma la contraseña'),
        ];
        $request = new SolicitudUsuario;
        $validator = Validator::make($data, ['nombre' => ['required', 'string', 'max:255'], 'correo' => ['required', 'email', 'max:255', 'unique:usuarios'], 'contrasena' => ['required', ...SolicitudUsuario::passwordRules()]], $request->messages(), $request->attributes());
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        $security->run(null, function () use ($security, $rol, $data) {
            $rol = Rol::whereKey($rol->id)->lockForUpdate()->firstOrFail();
            if ($security->hasAdministrativeAccess() || ! $rol->activo || array_diff(CatalogoPermisos::ADMINISTRATIVE_ACCESS, $rol->codigosPermisosAsignados()) !== []) {
                throw ValidationException::withMessages(['correo' => 'El acceso administrativo cambió. Revisa las cuentas existentes.']);
            }
            $usuario = Usuario::create(['nombre' => $data['nombre'], 'correo' => $data['correo'], 'contrasena' => $data['contrasena'], 'rol_id' => $rol->id, 'activo' => true]);
            $audit = app(AuditoriaAdministrativa::class);
            $audit->record(null, 'usuarios.bootstrap_created', $usuario, null, $audit->datosUsuario($usuario), 'console');
        });
        $this->info('Administrador creado.');

        return self::SUCCESS;
    }
}
