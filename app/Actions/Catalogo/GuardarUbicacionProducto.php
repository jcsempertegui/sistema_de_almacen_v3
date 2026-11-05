<?php

namespace App\Actions\Catalogo;

use App\Models\Producto;
use App\Models\Ubicacion;
use App\Models\UbicacionProducto;
use App\Models\Usuario;
use App\Services\JerarquiaUbicaciones;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasInventario;
use App\Services\ReferenciasProducto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarUbicacionProducto
{
    public function __construct(private ModificacionSegura $security, private JerarquiaUbicaciones $hierarchy, private ReferenciasProducto $references) {}

    public function handle(Usuario $actor, Producto $padre, ?UbicacionProducto $target, array $data): UbicacionProducto
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($padre, $target, $data) {
                $producto = Producto::whereKey($padre->id)->lockForUpdate()->firstOrFail();
                $this->security->exigirPermisos($operator, $target ? 'product_locations.change_status' : 'product_locations.create');
                abort_unless((string) ($data['version_bloqueo'] ?? '') === (string) $producto->version_bloqueo, 409, 'El producto cambió. Recarga el formulario.');
                $link = $target ? UbicacionProducto::whereKey($target->id)->lockForUpdate()->firstOrFail() : new UbicacionProducto;
                abort_if($target && $link->producto_id !== $producto->id, 404);
                if ($target && isset($data['ubicacion_id']) && (int) $data['ubicacion_id'] !== $link->ubicacion_id) {
                    throw ValidationException::withMessages(['ubicacion_id' => 'La ubicación de una asociación histórica no se sustituye.']);
                }
                if (! $target) {
                    $link->producto_id = $producto->id;
                    $link->ubicacion_id = $data['ubicacion_id'];
                    $link->creado_por = $operator->id;
                }
                $link->activo = $data['activo'];
                if ($target && ! $link->activo) {
                    app(ReferenciasInventario::class)->protectPair($producto->id, $link->ubicacion_id);
                }
                if (! $target && ! $link->activo) {
                    throw ValidationException::withMessages(['activo' => 'Las asociaciones nuevas se registran activas.']);
                }
                $ubicacion = Ubicacion::whereKey($link->ubicacion_id)->lockForUpdate()->first();
                if (! $ubicacion) {
                    throw ValidationException::withMessages(['ubicacion_id' => 'Selecciona una ubicación existente.']);
                }
                if ($link->activo) {
                    if (! $producto->activo || ! $ubicacion->activo || ! $ubicacion->permite_almacenar) {
                        throw ValidationException::withMessages(['ubicacion_id' => 'El producto y la ubicación deben estar activos y la ubicación debe permitir almacenar.']);
                    }
                    $this->hierarchy->validateParent($ubicacion, true);
                } elseif ($target && $this->references->hasAssignedUnits($producto->id, $ubicacion->id)) {
                    throw ValidationException::withMessages(['activo' => 'No puedes desactivar una asociación con unidades asignadas.']);
                }
                if (! $link->exists || $link->isDirty()) {
                    $link->modificado_por = $operator->id;
                    $link->save();
                    $producto->version_bloqueo++;
                    $producto->modificado_por = $operator->id;
                    $producto->save();
                }

                return $link;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['ubicacion_id' => 'Esta asociación ya existe, incluso si está inactiva. Reactiva el registro existente.']);
        }
    }
}
