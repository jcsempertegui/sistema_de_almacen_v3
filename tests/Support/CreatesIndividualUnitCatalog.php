<?php

namespace Tests\Support;

use App\Actions\Catalog\SaveIndividualUnit;
use App\Models\IndividualUnit;
use App\Models\Product;
use App\Models\User;

trait CreatesIndividualUnitCatalog
{
    private function individualData(array $changes = []): array
    {
        return array_replace(['identifier' => 'IND-'.strtoupper(bin2hex(random_bytes(6))), 'product_id' => $changes['product_id'] ?? Product::factory()->create(['control_mode' => 'individual'])->id, 'serial_number' => null, 'location_id' => null, 'notes' => null, 'is_active' => 1], $changes);
    }

    private function individualPayload(IndividualUnit $unit, array $changes = []): array
    {
        return array_replace(array_intersect_key($unit->getAttributes(), array_flip($unit->getFillable())), ['lock_version' => $unit->lock_version], $changes);
    }

    private function savedIndividual(User $actor, array $changes = []): IndividualUnit
    {
        return app(SaveIndividualUnit::class)->handle($actor, null, $this->individualData($changes));
    }
}
