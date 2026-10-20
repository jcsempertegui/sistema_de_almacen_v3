<?php

namespace App\Support;

final class ControlPermissions
{
    public const LABELS = [
        'adjustments.view' => 'Consultar ajustes', 'adjustments.create' => 'Crear ajustes', 'adjustments.update' => 'Editar ajustes', 'adjustments.request' => 'Solicitar ajustes', 'adjustments.execute' => 'Ejecutar ajustes autorizados', 'adjustments.cancel' => 'Cancelar ajustes pendientes',
        'compensations.view' => 'Consultar compensaciones', 'compensations.create' => 'Crear compensaciones', 'compensations.update' => 'Editar compensaciones', 'compensations.request' => 'Solicitar compensaciones', 'compensations.execute' => 'Ejecutar compensaciones autorizadas', 'compensations.cancel' => 'Cancelar compensaciones pendientes',
        'corrections.authorize' => 'Autorizar correcciones críticas', 'corrections.revoke' => 'Revocar autorizaciones críticas',
        'reports.stock.view' => 'Consultar reporte de existencias', 'reports.stock.export' => 'Exportar existencias',
        'reports.movements.view' => 'Consultar reporte de movimientos', 'reports.movements.export' => 'Exportar movimientos',
        'reports.remittances.view' => 'Consultar reporte de remitos', 'reports.remittances.export' => 'Exportar remitos',
        'reports.deliveries.view' => 'Consultar reporte de entregas', 'reports.deliveries.export' => 'Exportar entregas',
        'reports.loans.view' => 'Consultar reporte de préstamos', 'reports.loans.export' => 'Exportar préstamos',
        'reports.returns.view' => 'Consultar reporte de devoluciones', 'reports.returns.export' => 'Exportar devoluciones',
        'reports.responsibilities.view' => 'Consultar reporte de responsabilidades', 'reports.responsibilities.export' => 'Exportar responsabilidades',
        'reports.corrections.view' => 'Consultar reporte de correcciones', 'reports.corrections.export' => 'Exportar correcciones',
        'reports.availability.view' => 'Consultar reporte de disponibilidad', 'reports.availability.export' => 'Exportar disponibilidad',
        'backups.view' => 'Consultar respaldos', 'backups.generate' => 'Generar respaldos', 'backups.download' => 'Descargar respaldos', 'backups.verify' => 'Verificar respaldos', 'backups.inspect' => 'Inspeccionar manifiestos de respaldo',
        'backup_rehearsals.request' => 'Solicitar ensayo de restauración', 'backup_rehearsals.authorize' => 'Autorizar ensayo de restauración', 'backup_rehearsals.execute' => 'Ejecutar ensayo aislado',
        'critical_audit.view' => 'Consultar auditoría crítica', 'critical_audit.export' => 'Exportar auditoría crítica',
    ];
}
