<?php

namespace App\Services;

class ControlReports
{
    public const LABELS = ['stock' => 'Existencias', 'movements' => 'Kardex detallado', 'remittances' => 'Remitos y aperturas', 'deliveries' => 'Entregas y dotaciones de EPP', 'loans' => 'Historial de préstamos', 'returns' => 'Devoluciones', 'responsibilities' => 'Responsabilidades pendientes', 'corrections' => 'Ajustes y compensaciones', 'availability' => 'Estado de herramientas', 'audit' => 'Auditoría crítica'];

    public function permission(string $kind, string $action = 'view'): string
    {
        abort_unless(isset(self::LABELS[$kind]), 404);

        return $kind === 'audit' ? 'critical_audit.'.$action : 'reports.'.$kind.'.'.$action;
    }

    public function query(string $kind, array $filters)
    {
        $this->permission($kind);

        return app(DetailedReportQuery::class)->query($kind, $filters);
    }

    public static function csvCell(mixed $value): string
    {
        $s = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-]|^[\t\r\n]/u', $s) ? "'".$s : $s;
    }
}
