<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Frozen migration data: do not depend on future application labels.
        $labels = [
            'categories.view' => 'Consultar categorías', 'categories.create' => 'Crear categorías',
            'categories.update' => 'Editar categorías', 'categories.change_status' => 'Cambiar estado de categorías',
            'measurement_units.view' => 'Consultar unidades de medida', 'measurement_units.create' => 'Crear unidades de medida',
            'measurement_units.update' => 'Editar unidades de medida', 'measurement_units.change_status' => 'Cambiar estado de unidades de medida',
        ];
        DB::transaction(function () use ($labels) {
            DB::table('permissions')->where('code', 'roles.assign_permissions')->lockForUpdate()->firstOrFail();
            foreach ($labels as $code => $name) {
                DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $name, 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()]);
            }
            $initial = ['administrador' => array_keys($labels), 'encargado' => array_keys($labels), 'consulta_auditor' => ['categories.view', 'measurement_units.view']];
            foreach ($initial as $code => $codes) {
                $role = DB::table('roles')->where('code', $code)->lockForUpdate()->first();
                if (! $role) {
                    continue;
                }
                $before = DB::table('permission_role')->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')->where('role_id', $role->id)->where('permission_role.is_active', true)->orderBy('permissions.code')->pluck('permissions.code')->all();
                $granted = [];
                foreach (DB::table('permissions')->whereIn('code', $codes)->orderBy('code')->get() as $permission) {
                    // A link already present (active OR revoked) is configuration.
                    // Never replace it or re-enable a previously revoked grant.
                    if (DB::table('permission_role')->where('role_id', $role->id)->where('permission_id', $permission->id)->exists()) {
                        continue;
                    }
                    DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $permission->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
                    $granted[] = $permission->code;
                }
                if (! $granted) {
                    continue;
                }
                $after = array_merge($before, $granted);
                sort($after);
                DB::table('roles')->where('id', $role->id)->update(['permissions_version' => $role->permissions_version + 1, 'updated_at' => now()]);
                DB::table('administrative_audit_logs')->insert([
                    'role_id' => $role->id, 'action' => 'roles.permissions_changed', 'origin' => 'migration',
                    'before_data' => json_encode(['code' => $code, 'permission_codes' => $before, 'permissions_version' => $role->permissions_version], JSON_THROW_ON_ERROR),
                    'after_data' => json_encode(['code' => $code, 'permission_codes' => $after, 'permissions_version' => $role->permissions_version + 1, 'granted_permissions' => $granted, 'revoked_permissions' => []], JSON_THROW_ON_ERROR), 'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException('No se revierten permisos borrando configuración o auditoría.');
    }
};
