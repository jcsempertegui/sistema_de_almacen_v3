<?php

namespace App\Actions\Catalog;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductLocation;
use App\Models\User;
use App\Services\LocationHierarchy;
use App\Services\ProductReferences;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveProductLocation
{
    public function __construct(private SecurityMutation $security, private LocationHierarchy $hierarchy, private ProductReferences $references) {}

    public function handle(User $actor, Product $parent, ?ProductLocation $target, array $data): ProductLocation
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($parent, $target, $data) {
                $product = Product::whereKey($parent->id)->lockForUpdate()->firstOrFail();
                $this->security->requirePermissions($operator, $target ? 'product_locations.change_status' : 'product_locations.create');
                abort_unless((string) ($data['lock_version'] ?? '') === (string) $product->lock_version, 409, 'El producto cambió. Recarga el formulario.');
                $link = $target ? ProductLocation::whereKey($target->id)->lockForUpdate()->firstOrFail() : new ProductLocation;
                abort_if($target && $link->product_id !== $product->id, 404);
                if ($target && isset($data['location_id']) && (int) $data['location_id'] !== $link->location_id) {
                    throw ValidationException::withMessages(['location_id' => 'La ubicación de una asociación histórica no se sustituye.']);
                }
                if (! $target) {
                    $link->product_id = $product->id;
                    $link->location_id = $data['location_id'];
                    $link->created_by = $operator->id;
                }
                $link->is_active = $data['is_active'];
                if (! $target && ! $link->is_active) {
                    throw ValidationException::withMessages(['is_active' => 'Las asociaciones nuevas se registran activas.']);
                }
                $location = Location::whereKey($link->location_id)->lockForUpdate()->first();
                if (! $location) {
                    throw ValidationException::withMessages(['location_id' => 'Selecciona una ubicación existente.']);
                }
                if ($link->is_active) {
                    if (! $product->is_active || ! $location->is_active || ! $location->is_storable) {
                        throw ValidationException::withMessages(['location_id' => 'El producto y la ubicación deben estar activos y la ubicación debe permitir almacenar.']);
                    }
                    $this->hierarchy->validateParent($location, true);
                } elseif ($target && $this->references->hasAssignedUnits($product->id, $location->id)) {
                    throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una asociación con unidades asignadas.']);
                }
                if (! $link->exists || $link->isDirty()) {
                    $link->updated_by = $operator->id;
                    $link->save();
                    $product->lock_version++;
                    $product->updated_by = $operator->id;
                    $product->save();
                }

                return $link;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['location_id' => 'Esta asociación ya existe, incluso si está inactiva. Reactiva el registro existente.']);
        }
    }
}
