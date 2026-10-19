<?php

namespace App\Support;

use App\Models\MaterialDelivery;
use App\Models\ToolLoan;
use App\Models\ToolReturn;

final class WorkerOperationTypes
{
    public const MODELS = ['deliveries' => MaterialDelivery::class, 'loans' => ToolLoan::class, 'returns' => ToolReturn::class];

    public const LABELS = ['deliveries' => 'Entregas', 'loans' => 'Préstamos', 'returns' => 'Devoluciones'];

    public static function kind(object $doc): string
    {
        return array_search($doc::class, self::MODELS, true) ?: throw new \LogicException('Documento no admitido.');
    }
}
