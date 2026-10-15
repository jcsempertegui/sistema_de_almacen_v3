<?php

namespace App\Support;

final class PermissionCatalog
{
    public const LABELS = [
        'products.view' => 'Consultar productos', 'products.create' => 'Crear productos',
        'products.update' => 'Editar productos', 'products.change_status' => 'Cambiar estado de productos',
        'product_locations.view' => 'Consultar ubicaciones de productos', 'product_locations.create' => 'Asociar ubicaciones a productos',
        'product_locations.change_status' => 'Cambiar estado de ubicaciones de productos',
        'locations.view' => 'Consultar ubicaciones',
        'locations.create' => 'Crear ubicaciones',
        'locations.update' => 'Editar ubicaciones',
        'locations.change_status' => 'Cambiar estado de ubicaciones',
        'attributes.view' => 'Consultar atributos técnicos',
        'attributes.create' => 'Crear atributos técnicos',
        'attributes.update' => 'Editar atributos técnicos',
        'attributes.change_status' => 'Cambiar estado de atributos técnicos',
        'category_attributes.view' => 'Consultar atributos por categoría',
        'category_attributes.create' => 'Asociar atributos a categorías',
        'category_attributes.update' => 'Editar atributos por categoría',
        'category_attributes.change_status' => 'Cambiar estado de atributos por categoría',
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
        'categories.view' => 'Consultar categorías',
        'categories.create' => 'Crear categorías',
        'categories.update' => 'Editar categorías',
        'categories.change_status' => 'Cambiar estado de categorías',
        'measurement_units.view' => 'Consultar unidades de medida',
        'measurement_units.create' => 'Crear unidades de medida',
        'measurement_units.update' => 'Editar unidades de medida',
        'measurement_units.change_status' => 'Cambiar estado de unidades de medida',
    ];

    public const ADMINISTRATIVE_ACCESS = [
        'panel.view', 'users.view', 'users.create', 'users.update',
        'users.change_status', 'users.assign_role', 'users.change_password',
        'roles.view', 'roles.create', 'roles.update', 'roles.change_status',
        'roles.assign_permissions',
    ];
}
