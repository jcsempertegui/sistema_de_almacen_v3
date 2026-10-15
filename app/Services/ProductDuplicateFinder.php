<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\TechnicalDecimal;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class ProductDuplicateFinder
{
    public function candidates(Product $product, array $values, Collection $definitions, bool $lock = false): Collection
    {
        $identity = $definitions->filter(fn ($d) => app(ProductAttributeValidator::class)->available($d) && $d->is_identity);
        if ($identity->isEmpty() || $identity->contains(fn ($d) => ($values[$d->id] ?? null) === null)) {
            return collect();
        }
        $query = Product::where('category_id', $product->category_id)->where('measurement_unit_id', $product->measurement_unit_id)
            ->where('control_mode', $product->control_mode)->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))->orderBy('id');
        $products = ($lock ? $query->lockForUpdate() : $query)->get();
        $query = ProductAttributeValue::whereIn('product_id', $products->pluck('id'))->where('is_active', true);
        $byProduct = ($lock ? $query->lockForUpdate() : $query)->get()->groupBy('product_id');

        return $products->filter(function ($candidate) use ($identity, $values, $byProduct) {
            $stored = $byProduct->get($candidate->id, collect())->keyBy('category_attribute_id');
            foreach ($identity as $definition) {
                $row = $stored->get($definition->id);
                if (! $row) {
                    return false;
                }
                $type = $definition->attribute->data_type;
                $value = $row->getRawOriginal('value_'.$type);
                if ($type === 'decimal') {
                    $value = TechnicalDecimal::normalize($value, 'values');
                }
                if ($type === 'boolean') {
                    $value = (int) $value;
                }
                if ($value !== $values[$definition->id]) {
                    return false;
                }
            }
            $candidate->setRelation('attributeValues', $stored->values());

            return true;
        })->values();
    }

    public function token(array $data, array $values, Collection $definitions, Collection $candidates): string
    {
        return Crypt::encryptString($this->digest($data, $values, $definitions, $candidates));
    }

    public function requireReview(array $data, array $values, Collection $definitions, Collection $candidates): void
    {
        if ($candidates->isEmpty()) {
            return;
        }
        try {
            $review = Crypt::decryptString($data['duplicate_token'] ?? '');
        } catch (DecryptException) {
            $review = '';
        }
        if (! hash_equals($this->digest($data, $values, $definitions, $candidates), $review)) {
            throw ValidationException::withMessages(['duplicate_token' => 'Hay productos con características coincidentes. Revisa los candidatos antes de guardar un producto distinto.']);
        }
    }

    private function digest(array $data, array $values, Collection $definitions, Collection $candidates): string
    {
        unset($data['duplicate_token']);
        $payload = [$data, $values, $definitions->map(fn ($d) => [$d->id, $d->is_identity, $d->is_active, $d->updated_at?->toISOString()])->all(), $candidates->map(fn ($candidate) => [$candidate->id, $candidate->lock_version])->all()];
        $sort = function (array &$array) use (&$sort): void {
            ksort($array);
            foreach ($array as &$value) {
                if (is_array($value)) {
                    $sort($value);
                }
            }
        };
        $sort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
