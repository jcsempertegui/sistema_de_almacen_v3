<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\User;
use App\Services\ProductAttributeHistory;
use App\Services\ProductAttributeValidator;
use App\Services\ProductDuplicateFinder;
use App\Services\ProductReferences;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveProduct
{
    public function __construct(private SecurityMutation $security, private ProductAttributeValidator $attributes, private ProductAttributeHistory $history, private ProductDuplicateFinder $duplicates, private ProductReferences $references) {}

    public function handle(User $actor, ?Product $target, array $data, bool $statusOnly = false): Product
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $product = $target ? Product::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Product;
                $this->security->requirePermissions($operator, ! $target ? 'products.create' : ($statusOnly ? 'products.change_status' : 'products.update'));
                if ($target) {
                    abort_unless((string) ($data['lock_version'] ?? '') === (string) $product->lock_version, 409, 'El producto cambió. Recarga el formulario.');
                }
                $product->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : $product->getFillable())));
                if ($target && $product->isDirty('internal_code')) {
                    throw ValidationException::withMessages(['internal_code' => 'El código interno no se modifica ni se reutiliza.']);
                }
                if (! array_key_exists($product->control_mode, Product::MODES) || ($product->is_loanable && $product->control_mode !== 'individual')) {
                    throw ValidationException::withMessages(['control_mode' => 'Todo producto prestable debe utilizar control individual.']);
                }
                if ($target && $product->isDirty(['category_id', 'measurement_unit_id', 'control_mode', 'is_loanable']) && $this->references->hasHistory($product)) {
                    throw ValidationException::withMessages(['category_id' => 'Las referencias históricas impiden reinterpretar categoría, unidad o modo de control. Registra otro producto.']);
                }
                if ($target && $product->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'products.change_status');
                }
                foreach (['category_id' => Category::class, 'measurement_unit_id' => MeasurementUnit::class] as $key => $model) {
                    $reference = $model::whereKey($product->$key)->lockForUpdate()->first();
                    if (! $reference || (! $reference->is_active && (! $target || $product->is_active || $product->isDirty($key)))) {
                        throw ValidationException::withMessages([$key => 'Selecciona una referencia activa.']);
                    }
                }
                $definitions = $this->attributes->definitions($product->category_id, lock: true);
                $existing = $target ? $product->attributeValues()->lockForUpdate()->get()->keyBy('category_attribute_id') : collect();
                $input = $data['values'] ?? [];
                if ($statusOnly) {
                    $input = [];
                    foreach ($definitions as $definition) {
                        $row = $existing->get($definition->id);
                        if ($this->attributes->available($definition) && $row?->is_active) {
                            $input[$definition->id] = $row->getRawOriginal('value_'.$definition->attribute->data_type);
                        }
                    }
                }
                $values = $this->attributes->normalize($product, $input, $definitions);
                if (! $statusOnly) {
                    $this->duplicates->requireReview($data, $values, $definitions, $this->duplicates->candidates($product, $values, $definitions, lock: true));
                }
                $changed = ! $target || $product->isDirty();
                if (! $target) {
                    $product->created_by = $operator->id;
                }
                if (! $target || $product->isDirty()) {
                    $product->updated_by = $operator->id;
                    $product->save();
                }
                if (! $statusOnly) {
                    foreach ($definitions as $definition) {
                        if (! $this->attributes->available($definition)) {
                            continue;
                        }
                        $value = $values[$definition->id] ?? null;
                        $row = $existing->get($definition->id);
                        if ($value === null && ! $row) {
                            continue;
                        }
                        if (! $row) {
                            $row = new ProductAttributeValue;
                            $row->product_id = $product->id;
                            $row->category_id = $product->category_id;
                            $row->category_attribute_id = $definition->id;
                            $row->created_by = $operator->id;
                        }
                        $row->is_active = $value !== null;
                        if ($value !== null) {
                            foreach (ProductAttributeValue::VALUE_COLUMNS as $column) {
                                $row->$column = null;
                            }
                            $column = 'value_'.$definition->attribute->data_type;
                            $row->$column = $value;
                        }
                        if (! $row->exists || $row->isDirty()) {
                            $row->updated_by = $operator->id;
                            $row->save();
                            $this->history->record($row, $operator);
                            $changed = true;
                        }
                    }
                }
                if (! $target && ! empty($data['location_ids'])) {
                    foreach ($data['location_ids'] as $id) {
                        app(SaveProductLocation::class)->handle($operator, $product, null, ['location_id' => $id, 'is_active' => true, 'lock_version' => $product->fresh()->lock_version]);
                    }
                    $product->refresh();
                } elseif ($target && ! empty($data['location_ids'])) {
                    throw ValidationException::withMessages(['location_ids' => 'Gestiona las ubicaciones desde las asociaciones del producto.']);
                }
                if ($target && $changed) {
                    $product->lock_version++;
                    $product->updated_by = $operator->id;
                    $product->save();
                }

                return $product;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['internal_code' => 'El código interno ya está registrado, incluso si el producto está inactivo.']);
        }
    }
}
