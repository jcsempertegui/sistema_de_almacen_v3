<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Frozen migration data: do not depend on future application labels.
        $labels = [
            'adjustments.view' => 'Consultar ajustes',
            'adjustments.create' => 'Crear ajustes',
            'adjustments.update' => 'Editar ajustes',
            'adjustments.request' => 'Solicitar ajustes',
            'adjustments.execute' => 'Ejecutar ajustes autorizados',
            'adjustments.cancel' => 'Cancelar ajustes pendientes',
            'compensations.view' => 'Consultar compensaciones',
            'compensations.create' => 'Crear compensaciones',
            'compensations.update' => 'Editar compensaciones',
            'compensations.request' => 'Solicitar compensaciones',
            'compensations.execute' => 'Ejecutar compensaciones autorizadas',
            'compensations.cancel' => 'Cancelar compensaciones pendientes',
            'corrections.authorize' => 'Autorizar correcciones críticas',
            'corrections.revoke' => 'Revocar autorizaciones críticas',
            'reports.stock.view' => 'Consultar reporte de existencias',
            'reports.stock.export' => 'Exportar existencias',
            'reports.movements.view' => 'Consultar reporte de movimientos',
            'reports.movements.export' => 'Exportar movimientos',
            'reports.remittances.view' => 'Consultar reporte de remitos',
            'reports.remittances.export' => 'Exportar remitos',
            'reports.deliveries.view' => 'Consultar reporte de entregas',
            'reports.deliveries.export' => 'Exportar entregas',
            'reports.loans.view' => 'Consultar reporte de préstamos',
            'reports.loans.export' => 'Exportar préstamos',
            'reports.returns.view' => 'Consultar reporte de devoluciones',
            'reports.returns.export' => 'Exportar devoluciones',
            'reports.responsibilities.view' => 'Consultar reporte de responsabilidades',
            'reports.responsibilities.export' => 'Exportar responsabilidades',
            'reports.corrections.view' => 'Consultar reporte de correcciones',
            'reports.corrections.export' => 'Exportar correcciones',
            'reports.availability.view' => 'Consultar reporte de disponibilidad',
            'reports.availability.export' => 'Exportar disponibilidad',
            'backups.view' => 'Consultar respaldos',
            'backups.generate' => 'Generar respaldos',
            'backups.download' => 'Descargar respaldos',
            'backups.verify' => 'Verificar respaldos',
            'backups.inspect' => 'Inspeccionar manifiestos de respaldo',
            'backup_rehearsals.request' => 'Solicitar ensayo de restauración',
            'backup_rehearsals.authorize' => 'Autorizar ensayo de restauración',
            'backup_rehearsals.execute' => 'Ejecutar ensayo aislado',
            'critical_audit.view' => 'Consultar auditoría crítica',
            'critical_audit.export' => 'Exportar auditoría crítica',
        ];
        DB::transaction(function () use ($labels) {
            DB::table('permissions')->where('code', 'roles.assign_permissions')->lockForUpdate()->firstOrFail();
            foreach ($labels as $code => $name) {
                DB::table('permissions')->insertOrIgnore(['code' => $code, 'name' => $name, 'description' => $name.'.', 'created_at' => now(), 'updated_at' => now()]);
            }
            $initial = [
                'administrador' => [
                    0 => 'adjustments.view',
                    1 => 'adjustments.create',
                    2 => 'adjustments.update',
                    3 => 'adjustments.request',
                    4 => 'adjustments.execute',
                    5 => 'adjustments.cancel',
                    6 => 'compensations.view',
                    7 => 'compensations.create',
                    8 => 'compensations.update',
                    9 => 'compensations.request',
                    10 => 'compensations.execute',
                    11 => 'compensations.cancel',
                    12 => 'corrections.authorize',
                    13 => 'corrections.revoke',
                    14 => 'reports.stock.view',
                    15 => 'reports.stock.export',
                    16 => 'reports.movements.view',
                    17 => 'reports.movements.export',
                    18 => 'reports.remittances.view',
                    19 => 'reports.remittances.export',
                    20 => 'reports.deliveries.view',
                    21 => 'reports.deliveries.export',
                    22 => 'reports.loans.view',
                    23 => 'reports.loans.export',
                    24 => 'reports.returns.view',
                    25 => 'reports.returns.export',
                    26 => 'reports.responsibilities.view',
                    27 => 'reports.responsibilities.export',
                    28 => 'reports.corrections.view',
                    29 => 'reports.corrections.export',
                    30 => 'reports.availability.view',
                    31 => 'reports.availability.export',
                    32 => 'backups.view',
                    33 => 'backups.generate',
                    34 => 'backups.download',
                    35 => 'backups.verify',
                    36 => 'backups.inspect',
                    37 => 'backup_rehearsals.request',
                    38 => 'backup_rehearsals.authorize',
                    39 => 'backup_rehearsals.execute',
                    40 => 'critical_audit.view',
                    41 => 'critical_audit.export',
                ],
                'encargado' => [
                    0 => 'adjustments.view',
                    1 => 'adjustments.create',
                    2 => 'adjustments.update',
                    3 => 'adjustments.request',
                    4 => 'adjustments.execute',
                    5 => 'adjustments.cancel',
                    6 => 'compensations.view',
                    7 => 'compensations.create',
                    8 => 'compensations.update',
                    9 => 'compensations.request',
                    10 => 'compensations.execute',
                    11 => 'compensations.cancel',
                    12 => 'reports.stock.view',
                    13 => 'reports.stock.export',
                    14 => 'reports.movements.view',
                    15 => 'reports.movements.export',
                    16 => 'reports.remittances.view',
                    17 => 'reports.remittances.export',
                    18 => 'reports.deliveries.view',
                    19 => 'reports.deliveries.export',
                    20 => 'reports.loans.view',
                    21 => 'reports.loans.export',
                    22 => 'reports.returns.view',
                    23 => 'reports.returns.export',
                    24 => 'reports.responsibilities.view',
                    25 => 'reports.responsibilities.export',
                    26 => 'reports.corrections.view',
                    27 => 'reports.corrections.export',
                    28 => 'reports.availability.view',
                    29 => 'reports.availability.export',
                ],
                'consulta_auditor' => [
                    0 => 'adjustments.view',
                    1 => 'compensations.view',
                    2 => 'reports.stock.view',
                    3 => 'reports.stock.export',
                    4 => 'reports.movements.view',
                    5 => 'reports.movements.export',
                    6 => 'reports.remittances.view',
                    7 => 'reports.remittances.export',
                    8 => 'reports.deliveries.view',
                    9 => 'reports.deliveries.export',
                    10 => 'reports.loans.view',
                    11 => 'reports.loans.export',
                    12 => 'reports.returns.view',
                    13 => 'reports.returns.export',
                    14 => 'reports.responsibilities.view',
                    15 => 'reports.responsibilities.export',
                    16 => 'reports.corrections.view',
                    17 => 'reports.corrections.export',
                    18 => 'reports.availability.view',
                    19 => 'reports.availability.export',
                    20 => 'critical_audit.view',
                    21 => 'critical_audit.export',
                ],
            ];
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
