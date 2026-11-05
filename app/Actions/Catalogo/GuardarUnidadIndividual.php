<?php

namespace App\Actions\Catalogo;

use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\UnidadIndividual;
use App\Models\Usuario;
use App\Services\JerarquiaUbicaciones;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasInventario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GuardarUnidadIndividual
{
    public function __construct(private ModificacionSegura $security, private JerarquiaUbicaciones $hierarchy) {}

    public function handle(Usuario $actor, ?UnidadIndividual $target, array $data, bool $statusOnly = false): UnidadIndividual
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $this->security->exigirPermisos($operator, ! $target ? 'individual_units.create' : ($statusOnly ? 'individual_units.change_status' : 'individual_units.update'));
                $unidad = $target ? UnidadIndividual::whereKey($target->id)->lockForUpdate()->firstOrFail() : new UnidadIndividual;
                if ($target) {
                    abort_unless((string) ($data['version_bloqueo'] ?? '') === (string) $unidad->version_bloqueo, 409, 'La unidad cambió. Recarga el formulario.');
                }
                if (! $statusOnly) {
                    $data['identificador'] = is_string($data['identificador'] ?? null) ? Str::upper(trim($data['identificador'])) : ($data['identificador'] ?? null);
                    $data['numero_serie'] = is_string($data['numero_serie'] ?? null) ? (trim($data['numero_serie']) === '' ? null : trim($data['numero_serie'])) : ($data['numero_serie'] ?? null);
                    $data['ubicacion_id'] = ($data['ubicacion_id'] ?? null) === '' ? null : ($data['ubicacion_id'] ?? null);
                    Validator::make($data, [
                        'identificador' => ['required', 'string', 'max:64'], 'producto_id' => ['required', 'integer'],
                        'numero_serie' => ['nullable', 'string', 'max:100'], 'ubicacion_id' => ['nullable', 'integer'],
                        'observaciones' => ['nullable', 'string', 'max:10000'], 'activo' => ['required', 'boolean'],
                    ], ['required' => 'El campo :attribute es obligatorio.', 'string' => 'El campo :attribute debe ser texto.', 'max' => 'El campo :attribute supera la longitud permitida.', 'integer' => 'El campo :attribute debe ser un identificador válido.', 'boolean' => 'El estado debe ser activo o inactivo.'])->validate();
                    if ($target) {
                        foreach (['identificador', 'producto_id', 'ubicacion_id'] as $key) {
                            if ((string) $data[$key] !== (string) $unidad->$key) {
                                throw ValidationException::withMessages([$key => 'La identidad, el producto y la ubicación inicial no se modifican desde el catálogo.']);
                            }
                        }
                        if ($unidad->numero_serie !== null && $data['numero_serie'] !== $unidad->numero_serie) {
                            throw ValidationException::withMessages(['numero_serie' => 'Una serie informada no se sustituye ni se borra desde el catálogo.']);
                        }
                    }
                    $unidad->fill(array_intersect_key($data, array_flip($unidad->getFillable())));
                } else {
                    Validator::make($data, ['activo' => ['required', 'boolean']], ['required' => 'El estado es obligatorio.', 'boolean' => 'El estado debe ser activo o inactivo.'])->validate();
                    $unidad->activo = $data['activo'];
                }
                if ($target && $unidad->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'individual_units.change_status');
                    if (! $unidad->activo) {
                        app(ReferenciasInventario::class)->protectUnit($unidad->id);
                    }
                }
                // Current locking reads are essential after waiting on the shared security mutex.
                $producto = Producto::whereKey($unidad->producto_id)->lockForUpdate()->first();
                if (! $producto || (! $target || $unidad->activo) && (! $producto->activo || $producto->modo_control !== 'individual')) {
                    throw ValidationException::withMessages(['producto_id' => 'Selecciona un producto activo con control individual.']);
                }
                if ((! $target || $unidad->activo) && $unidad->ubicacion_id !== null) {
                    $link = UbicacionProducto::where('producto_id', $producto->id)->where('ubicacion_id', $unidad->ubicacion_id)->lockForUpdate()->first();
                    $ubicacion = Ubicacion::whereKey($unidad->ubicacion_id)->lockForUpdate()->first();
                    if (! $link?->activo || ! $ubicacion?->activo || ! $ubicacion->permite_almacenar) {
                        throw ValidationException::withMessages(['ubicacion_id' => 'Selecciona una ubicación activa, almacenable y asociada activamente al producto.']);
                    }
                    $this->hierarchy->validateParent($ubicacion, true);
                }
                if (! $target) {
                    $unidad->creado_por = $operator->id;
                }
                if (! $unidad->exists || $unidad->isDirty()) {
                    if ($unidad->exists) {
                        $unidad->version_bloqueo++;
                    }
                    $unidad->modificado_por = $operator->id;
                    $unidad->save();
                }

                return $unidad;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException $exception) {
            $field = match (true) {
                str_contains($exception->getMessage(), 'individual_units_identifier_unique') => 'identificador',
                str_contains($exception->getMessage(), 'individual_units_product_serial_unique') => 'numero_serie',
                default => null,
            };
            if ($field === null) {
                throw $exception;
            }
            throw ValidationException::withMessages([$field => $field === 'identificador' ? 'El identificador ya está reservado, incluso si la unidad está inactiva.' : 'La serie ya existe para este producto, incluso en una unidad inactiva.']);
        }
    }
}
