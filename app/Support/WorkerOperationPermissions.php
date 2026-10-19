<?php

namespace App\Support;

final class WorkerOperationPermissions
{
    public const LABELS = [
        'deliveries.view' => 'Consultar entregas',
        'deliveries.create' => 'Crear entregas',
        'deliveries.update' => 'Editar borradores de entregas',
        'deliveries.cancel_draft' => 'Cancelar borradores de entregas',
        'deliveries.confirm' => 'Confirmar entregas',
        'loans.view' => 'Consultar prestamos',
        'loans.create' => 'Crear prestamos',
        'loans.update' => 'Editar borradores de prestamos',
        'loans.cancel_draft' => 'Cancelar borradores de prestamos',
        'loans.confirm' => 'Confirmar prestamos',
        'returns.view' => 'Consultar devoluciones',
        'returns.create' => 'Crear devoluciones',
        'returns.update' => 'Editar borradores de devoluciones',
        'returns.cancel_draft' => 'Cancelar borradores de devoluciones',
        'returns.confirm' => 'Confirmar devoluciones',
        'availability.view' => 'Consultar disponibilidad',
        'returns.request_regularization' => 'Solicitar regularizacion de devolucion',
        'returns.authorize_regularization' => 'Autorizar regularizacion de devolucion',
        'returns.revoke_regularization' => 'Revocar regularizacion de devolucion',
    ];
}
