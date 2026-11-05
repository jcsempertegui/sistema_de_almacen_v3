<?php

namespace App\Actions\Usuarios;

use App\Models\Rol;
use App\Models\Usuario;
use App\Services\AuditoriaAdministrativa;
use App\Services\ModificacionSegura;
use Illuminate\Validation\ValidationException;

class ActualizarUsuario
{
    public function __construct(private ModificacionSegura $security, private AuditoriaAdministrativa $audit) {}

    public function handle(Usuario $actor, Usuario $target, array $atributos): Usuario
    {
        return $this->security->run($actor, function (Usuario $operator) use ($target, $atributos) {
            $usuario = Usuario::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $this->security->exigirGestionRol($operator, $usuario->rol);
            $before = $this->audit->datosUsuario($usuario);
            $data = array_intersect_key($atributos, array_flip(['nombre', 'correo', 'contrasena', 'rol_id', 'activo']));
            if (empty($data['contrasena'])) {
                unset($data['contrasena']);
            }
            if (! $operator->tienePermiso('users.update') && ! $operator->tienePermiso('users.change_status')) {
                $this->security->exigirPermisos($operator, 'users.update');
            }
            if (array_key_exists('rol_id', $data) && (int) $data['rol_id'] !== $usuario->rol_id) {
                $this->security->exigirPermisos($operator, 'users.assign_role');
                $newRole = Rol::whereKey($data['rol_id'])->lockForUpdate()->firstOrFail();
                if (! $newRole->activo) {
                    throw ValidationException::withMessages(['rol_id' => 'Selecciona un rol activo.']);
                }
                $this->security->exigirGestionRol($operator, $newRole);
            }
            if (isset($data['contrasena'])) {
                $this->security->exigirPermisos($operator, 'users.change_password');
            }
            $usuario->fill($data);
            if ($usuario->isDirty(['nombre', 'correo'])) {
                $this->security->exigirPermisos($operator, 'users.update');
            }
            $statusChanged = $usuario->isDirty('activo');
            if ($statusChanged) {
                $this->security->exigirPermisos($operator, 'users.change_status');
            }
            $usuario->unsetRelation('rol');
            if ($statusChanged && $usuario->activo && ! $usuario->rol->activo) {
                throw ValidationException::withMessages(['rol_id' => 'No puedes activar una cuenta con un rol desactivado.']);
            }
            if (! $usuario->isDirty()) {
                return $usuario;
            }
            if ($usuario->isDirty(['contrasena', 'rol_id', 'activo', 'correo'])) {
                $usuario->version_sesion++;
            }
            $usuario->save();
            $after = $this->audit->datosUsuario($usuario);
            if (isset($data['contrasena'])) {
                $after['password_changed'] = true;
            }
            $this->audit->record($operator, $statusChanged ? 'usuarios.status_changed' : 'usuarios.updated', $usuario, $before, $after);

            return $usuario;
        });
    }
}
