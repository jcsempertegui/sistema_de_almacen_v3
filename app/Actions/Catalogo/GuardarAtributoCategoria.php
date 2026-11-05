<?php

namespace App\Actions\Catalogo;

use App\Models\AtributoCatalogo;
use App\Models\AtributoCategoria;
use App\Models\Categoria;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasAtributo;
use App\Support\DecimalTecnico;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarAtributoCategoria
{
    public function __construct(private ModificacionSegura $security, private ReferenciasAtributo $references) {}

    public function handle(Usuario $actor, Categoria $categoria, ?AtributoCategoria $target, array $data, bool $statusOnly = false): AtributoCategoria
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($categoria, $target, $data, $statusOnly) {
                $padre = Categoria::whereKey($categoria->id)->lockForUpdate()->firstOrFail();
                $association = $target ? AtributoCategoria::whereKey($target->id)->lockForUpdate()->firstOrFail() : new AtributoCategoria;
                abort_if($target && $association->categoria_id !== $padre->id, 404);
                $this->security->exigirPermisos($operator, ! $target ? 'category_attributes.create' : ($statusOnly ? 'category_attributes.change_status' : 'category_attributes.update'));
                if (array_key_exists('categoria_id', $data) || ($target && array_key_exists('atributo_id', $data))) {
                    throw ValidationException::withMessages(['atributo_id' => 'La categoría y el atributo de una asociación existente no se pueden sustituir.']);
                }
                $association->categoria_id = $padre->id;
                $association->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : ['atributo_id', 'obligatorio', 'identificacion_tecnica', 'orden_presentacion', 'valor_minimo', 'valor_maximo', 'activo'])));
                $atributo = AtributoCatalogo::whereKey($association->atributo_id)->lockForUpdate()->first();
                if (! $atributo) {
                    throw ValidationException::withMessages(['atributo_id' => 'Selecciona un atributo existente.']);
                }
                $unidad = $atributo->unidadMedida()->lockForUpdate()->first();
                $enabling = ! $target || ($association->isDirty('activo') && $association->activo);
                if ($enabling && (! $padre->activo || ! $atributo->activo || ($unidad && ! $unidad->activo))) {
                    throw ValidationException::withMessages(['atributo_id' => 'Para asociar o activar, la categoría, el atributo y su unidad técnica deben estar activos.']);
                }
                if ($target && $association->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'category_attributes.change_status');
                }
                if (! $statusOnly) {
                    $precision = $unidad?->decimales ?? 6;
                    $minimum = DecimalTecnico::normalize($association->getAttributes()['valor_minimo'] ?? null, 'valor_minimo', $precision);
                    $maximum = DecimalTecnico::normalize($association->getAttributes()['valor_maximo'] ?? null, 'valor_maximo', $precision);
                    if ($atributo->tipo_dato !== 'decimal' && ($minimum !== null || $maximum !== null)) {
                        throw ValidationException::withMessages(['valor_minimo' => 'Solo los atributos decimales admiten límites.']);
                    }
                    if ($minimum !== null && $maximum !== null && DecimalTecnico::compare($minimum, $maximum) > 0) {
                        throw ValidationException::withMessages(['valor_maximo' => 'El máximo debe ser mayor o igual al mínimo.']);
                    }
                    $association->valor_minimo = $minimum;
                    $association->valor_maximo = $maximum;
                    if ($target && $association->isDirty(['identificacion_tecnica', 'valor_minimo', 'valor_maximo']) && $this->references->associationHasValues($association->id)) {
                        throw ValidationException::withMessages(['identificacion_tecnica' => 'Esta configuración tiene valores históricos. Revisa el impacto antes de cambiar su identificación o sus límites.']);
                    }
                }
                if ($association->activo && $association->obligatorio && ($enabling || $association->isDirty('obligatorio')) && $this->references->categoryHasIncompleteProducts($association)) {
                    throw ValidationException::withMessages(['obligatorio' => 'Completa primero el atributo en todos los productos activos de la categoría.']);
                }

                if (! $target) {
                    $association->creado_por = $operator->id;
                }
                if (! $association->exists || $association->isDirty()) {
                    $association->modificado_por = $operator->id;
                    $association->save();
                }

                return $association;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['atributo_id' => 'Este atributo ya está asociado a la categoría, incluso si está inactivo. Edita o reactiva la asociación existente.']);
        }
    }
}
