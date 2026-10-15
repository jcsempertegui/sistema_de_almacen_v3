<?php

namespace Tests\Support;

use App\Models\CatalogAttribute;
use App\Models\Category;
use App\Models\CategoryAttribute;

trait CreatesTechnicalCatalog
{
    private function attributeData(array $changes = []): array
    {
        return array_replace(['code' => 'TEMP', 'name' => 'Temperatura', 'data_type' => 'decimal', 'measurement_unit_id' => null, 'help_text' => 'Temperatura técnica', 'is_active' => 1], $changes);
    }

    private function associationData(array $changes = []): array
    {
        return array_replace(['is_required' => 0, 'is_identity' => 0, 'display_order' => 0, 'min_value' => null, 'max_value' => null, 'is_active' => 1], $changes);
    }

    private function technicalAttribute(array $changes = []): CatalogAttribute
    {
        return CatalogAttribute::create($this->attributeData(array_replace(['code' => 'ATTR-'.bin2hex(random_bytes(4))], $changes)));
    }

    private function association(Category $category, CatalogAttribute $attribute, array $changes = []): CategoryAttribute
    {
        $association = new CategoryAttribute($this->associationData(array_replace(['attribute_id' => $attribute->id], $changes)));
        $association->category()->associate($category);
        $association->save();

        return $association;
    }
}
