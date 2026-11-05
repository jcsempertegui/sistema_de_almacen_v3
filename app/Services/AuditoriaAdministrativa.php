<?php

namespace App\Services;

use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Rol;
use App\Models\Usuario;

class AuditoriaAdministrativa
{
    public function datosUsuario(Usuario $usuario): array
    {
        return ['nombre' => $usuario->nombre, 'correo' => $usuario->correo, 'rol_id' => $usuario->rol_id, 'activo' => $usuario->activo];
    }

    public function datosRol(Rol $rol): array
    {
        return ['codigo' => $rol->codigo, 'nombre' => $rol->nombre, 'descripcion' => $rol->descripcion, 'activo' => $rol->activo, 'version_permisos' => $rol->version_permisos, 'permission_codes' => $rol->codigosPermisosAsignados()];
    }

    public function record(?Usuario $actor, string $accion, Usuario|Rol $subject, ?array $before, array $after, string $origen = 'web'): void
    {
        RegistroAuditoriaAdministrativa::create([
            'actor_id' => $actor?->id, 'nombre_actor' => $actor?->nombre,
            'usuario_id' => $subject instanceof Usuario ? $subject->id : null,
            'rol_id' => $subject instanceof Rol ? $subject->id : null,
            'accion' => $accion, 'origen' => $origen,
            'direccion_ip' => $origen === 'web' ? request()->ip() : null,
            'datos_anteriores' => $before, 'datos_posteriores' => $after, 'fecha_creacion' => now(),
        ]);
    }
}
