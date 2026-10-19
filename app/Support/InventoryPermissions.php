<?php

namespace App\Support;

final class InventoryPermissions
{
    public const LABELS = [
        'remittances.view' => 'Consultar remitos',
        'remittances.create' => 'Crear remitos',
        'remittances.update' => 'Editar remitos en borrador',
        'remittances.cancel_draft' => 'Cancelar remitos en borrador',
        'remittances.confirm_receipt' => 'Confirmar remitos de ingreso',
        'remittances.confirm_issue' => 'Confirmar remitos de egreso',
        'stock.view' => 'Consultar existencias',
        'movements.view' => 'Consultar movimientos',
        'initial_stock.view' => 'Consultar aperturas',
        'initial_stock.create' => 'Crear aperturas',
        'initial_stock.update' => 'Editar aperturas en borrador',
        'initial_stock.import' => 'Importar apertura CSV',
        'initial_stock.confirm' => 'Confirmar aperturas',
        'initial_stock.cancel_draft' => 'Cancelar aperturas en borrador',
    ];
}
