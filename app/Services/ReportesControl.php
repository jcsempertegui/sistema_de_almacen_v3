<?php

namespace App\Services;

class ReportesControl
{
    public const LABELS = ['stock' => 'Existencias', 'movements' => 'Kardex detallado', 'remittances' => 'Remitos y aperturas', 'deliveries' => 'Entregas y dotaciones de EPP', 'loans' => 'Historial de préstamos', 'returns' => 'Devoluciones', 'responsibilities' => 'Responsabilidades pendientes', 'corrections' => 'Ajustes y compensaciones', 'availability' => 'Estado de herramientas', 'audit' => 'Auditoría crítica'];

    public function permiso(string $clase, string $accion = 'view'): string
    {
        abort_unless(isset(self::LABELS[$clase]), 404);

        return $clase === 'audit' ? 'critical_audit.'.$accion : 'reports.'.$clase.'.'.$accion;
    }

    public function query(string $clase, array $filters)
    {
        $this->permiso($clase);

        return app(ConsultaReporteDetallado::class)->query($clase, $filters);
    }

    public static function csvCell(mixed $value): string
    {
        $s = (string) ($value ?? '');

        return preg_match('/^[\s]*[=+@\-]|^[\t\r\n]/u', $s) ? "'".$s : $s;
    }
}
