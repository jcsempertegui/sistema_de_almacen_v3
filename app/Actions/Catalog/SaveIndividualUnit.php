<?php

namespace App\Actions\Catalog;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\User;
use App\Services\InventoryReferences;
use App\Services\LocationHierarchy;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveIndividualUnit
{
    public function __construct(private SecurityMutation $security, private LocationHierarchy $hierarchy) {}

    public function handle(User $actor, ?IndividualUnit $target, array $data, bool $statusOnly = false): IndividualUnit
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $this->security->requirePermissions($operator, ! $target ? 'individual_units.create' : ($statusOnly ? 'individual_units.change_status' : 'individual_units.update'));
                $unit = $target ? IndividualUnit::whereKey($target->id)->lockForUpdate()->firstOrFail() : new IndividualUnit;
                if ($target) {
                    abort_unless((string) ($data['lock_version'] ?? '') === (string) $unit->lock_version, 409, 'La unidad cambió. Recarga el formulario.');
                }
                if (! $statusOnly) {
                    $data['identifier'] = is_string($data['identifier'] ?? null) ? Str::upper(trim($data['identifier'])) : ($data['identifier'] ?? null);
                    $data['serial_number'] = is_string($data['serial_number'] ?? null) ? (trim($data['serial_number']) === '' ? null : trim($data['serial_number'])) : ($data['serial_number'] ?? null);
                    $data['location_id'] = ($data['location_id'] ?? null) === '' ? null : ($data['location_id'] ?? null);
                    Validator::make($data, [
                        'identifier' => ['required', 'string', 'max:64'], 'product_id' => ['required', 'integer'],
                        'serial_number' => ['nullable', 'string', 'max:100'], 'location_id' => ['nullable', 'integer'],
                        'notes' => ['nullable', 'string', 'max:10000'], 'is_active' => ['required', 'boolean'],
                    ], ['required' => 'El campo :attribute es obligatorio.', 'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute supera la longitud permitida.', 'integer' => 'El campo :attribute debe ser un identificador válido.', 'boolean' => 'El estado debe ser activo o inactivo.'])->validate();
                    if ($target) {
                        foreach (['identifier', 'product_id', 'location_id'] as $key) {
                            if ((string) $data[$key] !== (string) $unit->$key) {
                                throw ValidationException::withMessages([$key => 'La identidad, el producto y la ubicación inicial no se modifican desde el catálogo.']);
                            }
                        }
                        if ($unit->serial_number !== null && $data['serial_number'] !== $unit->serial_number) {
                            throw ValidationException::withMessages(['serial_number' => 'Una serie informada no se sustituye ni se borra desde el catálogo.']);
                        }
                    }
                    $unit->fill(array_intersect_key($data, array_flip($unit->getFillable())));
                } else {
                    Validator::make($data, ['is_active' => ['required', 'boolean']], ['required' => 'El estado es obligatorio.', 'boolean' => 'El estado debe ser activo o inactivo.'])->validate();
                    $unit->is_active = $data['is_active'];
                }
                if ($target && $unit->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'individual_units.change_status');
                    if (! $unit->is_active) {
                        app(InventoryReferences::class)->protectUnit($unit->id);
                    }
                }
                // Current locking reads are essential after waiting on the shared security mutex.
                $product = Product::whereKey($unit->product_id)->lockForUpdate()->first();
                if (! $product || (! $target || $unit->is_active) && (! $product->is_active || $product->control_mode !== 'individual')) {
                    throw ValidationException::withMessages(['product_id' => 'Selecciona un producto activo con control individual.']);
                }
                if ((! $target || $unit->is_active) && $unit->location_id !== null) {
                    $link = ProductLocation::where('product_id', $product->id)->where('location_id', $unit->location_id)->lockForUpdate()->first();
                    $location = Location::whereKey($unit->location_id)->lockForUpdate()->first();
                    if (! $link?->is_active || ! $location?->is_active || ! $location->is_storable) {
                        throw ValidationException::withMessages(['location_id' => 'Selecciona una ubicación activa, almacenable y asociada activamente al producto.']);
                    }
                    $this->hierarchy->validateParent($location, true);
                }
                if (! $target) {
                    $unit->created_by = $operator->id;
                }
                if (! $unit->exists || $unit->isDirty()) {
                    if ($unit->exists) {
                        $unit->lock_version++;
                    }
                    $unit->updated_by = $operator->id;
                    $unit->save();
                }

                return $unit;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException $exception) {
            $field = match (true) {
                str_contains($exception->getMessage(), 'individual_units_identifier_unique') => 'identifier',
                str_contains($exception->getMessage(), 'individual_units_product_serial_unique') => 'serial_number',
                default => null,
            };
            if ($field === null) {
                throw $exception;
            }
            throw ValidationException::withMessages([$field => $field === 'identifier' ? 'El identificador ya está reservado, incluso si la unidad está inactiva.' : 'La serie ya existe para este producto, incluso en una unidad inactiva.']);
        }
    }
}
