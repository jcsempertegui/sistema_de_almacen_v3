<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Frozen initial catalog. Never depend on future application constants.
        $labels = [
            'panel.view' => 'Acceder al panel', 'users.view' => 'Consultar usuarios',
            'users.create' => 'Crear usuarios', 'users.update' => 'Editar datos de usuarios',
            'users.change_status' => 'Cambiar estado de usuarios', 'users.assign_role' => 'Asignar roles a usuarios',
            'users.change_password' => 'Cambiar contraseñas de usuarios', 'roles.view' => 'Consultar roles',
            'roles.create' => 'Crear roles', 'roles.update' => 'Editar datos de roles',
            'roles.change_status' => 'Cambiar estado de roles', 'roles.assign_permissions' => 'Asignar permisos a roles',
            'workers.view' => 'Consultar trabajadores', 'workers.create' => 'Crear trabajadores',
            'workers.update' => 'Editar trabajadores', 'workers.change_status' => 'Cambiar estado de trabajadores',
            'audit.view' => 'Consultar auditoría administrativa',
        ];
        DB::transaction(function () use ($labels) {
            foreach ($labels as $code => $name) {
                DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $name, 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()]);
            }
            $initial = [
                'administrador' => array_keys($labels),
                'encargado' => ['panel.view', 'workers.view', 'workers.create', 'workers.update', 'workers.change_status'],
                'consulta_auditor' => ['panel.view', 'workers.view', 'audit.view'],
            ];
            foreach ($initial as $code => $codes) {
                $role = DB::table('roles')->where('code', $code)->first();
                // Preserve unknown roles, and never restore configured/revoked links.
                if (! $role || DB::table('permission_role')->where('role_id', $role->id)->exists()) {
                    continue;
                }
                $ids = DB::table('permissions')->whereIn('code', $codes)->pluck('id');
                foreach ($ids as $id) {
                    DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                }
                DB::table('administrative_audit_logs')->insert([
                    'role_id' => $role->id, 'action' => 'rbac.initialized', 'origin' => 'migration',
                    'after_data' => json_encode(['code' => $code, 'permission_codes' => $codes], JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La inicialización de permisos no se revierte borrando configuraciones o auditoría.');
    }
};
