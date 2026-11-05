<?php

namespace App\Actions\Usuarios;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaAdministrativa;
use App\Services\ModificacionSegura;
use Illuminate\Validation\ValidationException;

class CrearUsuario
{
    public function __construct(private ModificacionSegura $security, private AuditoriaAdministrativa $audit) {}

    public function handle(Usuario $actor, array $data): Usuario
    {
        return $this->security->run($actor, function (Usuario $operator) use ($data) {
            $this->security->exigirPermisos($operator, 'users.create', 'users.assign_role');
            $rol = Rol::whereKey($data['rol_id'])->lockForUpdate()->firstOrFail();
            if (! $rol->activo) {
                throw ValidationException::withMessages(['rol_id' => 'Selecciona un rol activo.']);
            }
            $this->security->exigirGestionRol($operator, $rol);
            $usuario = Usuario::create(array_intersect_key($data, array_flip(['nombre', 'correo', 'contrasena', 'rol_id', 'activo'])));
            $usuario->refresh();
            $this->audit->record($operator, 'usuarios.created', $usuario, null, $this->audit->datosUsuario($usuario));

            return $usuario;
        });
    }
}
