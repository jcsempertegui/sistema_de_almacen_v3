<?php

namespace App\Support;

use App\Models\DevolucionHerramienta;
use App\Models\EntregaMaterial;
use App\Models\PrestamoHerramienta;

final class TiposOperacionTrabajador
{
    public const MODELS = ['deliveries' => EntregaMaterial::class, 'loans' => PrestamoHerramienta::class, 'returns' => DevolucionHerramienta::class];

    public const LABELS = ['deliveries' => 'Entregas', 'loans' => 'Préstamos', 'returns' => 'Devoluciones'];

    public static function tipo(object $doc): string
    {
        return array_search($doc::class, self::MODELS, true) ?: throw new \LogicException('Documento no admitido.');
    }
}
