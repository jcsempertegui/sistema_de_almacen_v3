<?php

namespace App\Actions\Catalogo;

use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasCatalogo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarUnidadMedida
{
    public function __construct(private ModificacionSegura $security, private ReferenciasCatalogo $references) {}

    public function handle(Usuario $actor, ?UnidadMedida $target, array $data, bool $statusOnly = false): UnidadMedida
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $unidad = $target ? UnidadMedida::whereKey($target->id)->lockForUpdate()->firstOrFail() : new UnidadMedida;
                $this->security->exigirPermisos($operator, ! $target ? 'measurement_units.create' : ($statusOnly ? 'measurement_units.change_status' : 'measurement_units.update'));
                $unidad->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : ['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo'])));
                if ($target && $unidad->isDirty(['codigo', 'simbolo', 'dimension', 'decimales']) && $this->references->unitHasAnyReferences($unidad->id)) {
                    throw ValidationException::withMessages(['unidad_medida' => 'Esta unidad ya tiene referencias. Conserva su código, símbolo, dimensión y precisión; registra otra unidad si cambia su significado.']);
                }
                if ($target && $unidad->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'measurement_units.change_status');
                    if (! $unidad->activo && $this->references->unitHasActiveReferences($unidad->id)) {
                        throw ValidationException::withMessages(['activo' => 'No puedes desactivar una unidad utilizada por productos o atributos activos.']);
                    }
                }
                if (! $target) {
                    $unidad->creado_por = $operator->id;
                }
                if (! $unidad->exists || $unidad->isDirty()) {
                    $unidad->modificado_por = $operator->id;
                    $unidad->save();
                }

                return $unidad;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            $query = UnidadMedida::where('codigo', $data['codigo'] ?? $target?->codigo)->when($target, fn ($query) => $query->whereKeyNot($target->id));
            throw ValidationException::withMessages([$query->exists() ? 'codigo' : 'simbolo' => 'El código o símbolo de unidad ya está registrado.']);
        }
    }
}
