<?php

namespace App\Support;

final class PermissionCatalog
{
    public const LABELS = [
        'panel.view' => 'Acceder al panel',
        'users.view' => 'Consultar usuarios',
        'users.create' => 'Crear usuarios',
        'users.update' => 'Editar datos de usuarios',
        'users.change_status' => 'Cambiar estado de usuarios',
        'users.assign_role' => 'Asignar roles a usuarios',
        'users.change_password' => 'Cambiar contraseñas de usuarios',
        'roles.view' => 'Consultar roles',
        'roles.create' => 'Crear roles',
        'roles.update' => 'Editar datos de roles',
        'roles.change_status' => 'Cambiar estado de roles',
        'roles.assign_permissions' => 'Asignar permisos a roles',
        'workers.view' => 'Consultar trabajadores',
        'workers.create' => 'Crear trabajadores',
        'workers.update' => 'Editar trabajadores',
        'workers.change_status' => 'Cambiar estado de trabajadores',
        'audit.view' => 'Consultar auditoría administrativa',
    ];

    public const ADMINISTRATIVE_ACCESS = [
        'panel.view', 'users.view', 'users.create', 'users.update',
        'users.change_status', 'users.assign_role', 'users.change_password',
        'roles.view', 'roles.create', 'roles.update', 'roles.change_status',
        'roles.assign_permissions',
    ];
}
