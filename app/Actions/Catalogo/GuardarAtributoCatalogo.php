<?php

namespace App\Actions\Catalogo;

use App\Models\AtributoCatalogo;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarAtributoCatalogo
{
    public function __construct(private ModificacionSegura $security) {}

    public function handle(Usuario $actor, ?AtributoCatalogo $target, array $data, bool $statusOnly = false): AtributoCatalogo
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $atributo = $target ? AtributoCatalogo::whereKey($target->id)->lockForUpdate()->firstOrFail() : new AtributoCatalogo;
                $this->security->exigirPermisos($operator, ! $target ? 'attributes.create' : ($statusOnly ? 'attributes.change_status' : 'attributes.update'));
                $atributo->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : ['codigo', 'nombre', 'tipo_dato', 'unidad_medida_id', 'texto_ayuda', 'activo'])));
                if ($target && $atributo->isDirty(['tipo_dato', 'unidad_medida_id']) && $atributo->atributosCategoria()->exists()) {
                    throw ValidationException::withMessages(['tipo_dato' => 'Este atributo tiene asociaciones actuales o históricas. Conserva su tipo y unidad; registra otro atributo si cambia su significado.']);
                }
                if ($atributo->unidad_medida_id && $atributo->tipo_dato !== 'decimal') {
                    throw ValidationException::withMessages(['unidad_medida_id' => 'Solo los atributos decimales admiten una unidad técnica.']);
                }
                if ($atributo->unidad_medida_id) {
                    $unidad = UnidadMedida::whereKey($atributo->unidad_medida_id)->lockForUpdate()->first();
                    if (! $unidad || (! $unidad->activo && (! $target || $atributo->activo || $atributo->isDirty('unidad_medida_id')))) {
                        throw ValidationException::withMessages(['unidad_medida_id' => 'Selecciona una unidad técnica activa.']);
                    }
                }
                if ($target && $atributo->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'attributes.change_status');
                    if (! $atributo->activo && $atributo->atributosCategoria()->where('activo', true)->exists()) {
                        throw ValidationException::withMessages(['activo' => 'Desactiva primero las asociaciones activas de este atributo en las categorías.']);
                    }
                }
                if (! $target) {
                    $atributo->creado_por = $operator->id;
                }
                if (! $atributo->exists || $atributo->isDirty()) {
                    $atributo->modificado_por = $operator->id;
                    $atributo->save();
                }

                return $atributo;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo' => 'El código del atributo ya está registrado.']);
        }
    }
}
