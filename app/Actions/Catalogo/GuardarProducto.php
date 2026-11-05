<?php

namespace App\Actions\Catalogo;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Models\ValorAtributoProducto;
use App\Services\BuscadorDuplicadosProducto;
use App\Services\HistorialAtributosProducto;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasInventario;
use App\Services\ReferenciasProducto;
use App\Services\ValidadorAtributosProducto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarProducto
{
    public function __construct(private ModificacionSegura $security, private ValidadorAtributosProducto $atributos, private HistorialAtributosProducto $history, private BuscadorDuplicadosProducto $duplicates, private ReferenciasProducto $references) {}

    public function handle(Usuario $actor, ?Producto $target, array $data, bool $statusOnly = false): Producto
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $producto = $target ? Producto::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Producto;
                $this->security->exigirPermisos($operator, ! $target ? 'products.create' : ($statusOnly ? 'products.change_status' : 'products.update'));
                if ($target) {
                    abort_unless((string) ($data['version_bloqueo'] ?? '') === (string) $producto->version_bloqueo, 409, 'El producto cambió. Recarga el formulario.');
                }
                $producto->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : $producto->getFillable())));
                if ($target && $producto->isDirty('codigo_interno')) {
                    throw ValidationException::withMessages(['codigo_interno' => 'El código interno no se modifica ni se reutiliza.']);
                }
                if (! array_key_exists($producto->modo_control, Producto::MODES) || ($producto->prestable && $producto->modo_control !== 'individual')) {
                    throw ValidationException::withMessages(['modo_control' => 'Todo producto prestable debe utilizar control individual.']);
                }
                if ($target && $producto->isDirty(['categoria_id', 'unidad_medida_id', 'modo_control', 'prestable']) && $this->references->hasHistory($producto)) {
                    throw ValidationException::withMessages(['categoria_id' => 'Las referencias históricas impiden reinterpretar categoría, unidad o modo de control. Registra otro producto.']);
                }
                if ($target && $producto->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'products.change_status');
                    if (! $producto->activo && app(ReferenciasInventario::class)->hasStock($producto->id)) {
                        throw ValidationException::withMessages(['activo' => 'No puedes desactivar un producto con existencias físicas.']);
                    }
                    if (! $producto->activo && $this->references->hasActiveUnits($producto->id)) {
                        throw ValidationException::withMessages(['activo' => 'No puedes desactivar un producto con unidades individualizadas activas.']);
                    }
                }
                foreach (['categoria_id' => Categoria::class, 'unidad_medida_id' => UnidadMedida::class] as $key => $model) {
                    $reference = $model::whereKey($producto->$key)->lockForUpdate()->first();
                    if (! $reference || (! $reference->activo && (! $target || $producto->activo || $producto->isDirty($key)))) {
                        throw ValidationException::withMessages([$key => 'Selecciona una referencia activa.']);
                    }
                }
                $definitions = $this->atributos->definitions($producto->categoria_id, lock: true);
                $existing = $target ? $producto->valoresAtributos()->lockForUpdate()->get()->keyBy('atributo_categoria_id') : collect();
                $input = $data['values'] ?? [];
                if ($statusOnly) {
                    $input = [];
                    foreach ($definitions as $definition) {
                        $row = $existing->get($definition->id);
                        if ($this->atributos->available($definition) && $row?->activo) {
                            $input[$definition->id] = $row->getRawOriginal(ValorAtributoProducto::columnaParaTipo($definition->atributo->tipo_dato));
                        }
                    }
                }
                $values = $this->atributos->normalize($producto, $input, $definitions);
                if (! $statusOnly) {
                    $this->duplicates->requireReview($data, $values, $definitions, $this->duplicates->candidates($producto, $values, $definitions, lock: true));
                }
                $changed = ! $target || $producto->isDirty();
                if (! $target) {
                    $producto->creado_por = $operator->id;
                }
                if (! $target || $producto->isDirty()) {
                    $producto->modificado_por = $operator->id;
                    $producto->save();
                }
                if (! $statusOnly) {
                    foreach ($definitions as $definition) {
                        if (! $this->atributos->available($definition)) {
                            continue;
                        }
                        $value = $values[$definition->id] ?? null;
                        $row = $existing->get($definition->id);
                        if ($value === null && ! $row) {
                            continue;
                        }
                        if (! $row) {
                            $row = new ValorAtributoProducto;
                            $row->producto_id = $producto->id;
                            $row->categoria_id = $producto->categoria_id;
                            $row->atributo_categoria_id = $definition->id;
                            $row->creado_por = $operator->id;
                        }
                        $row->activo = $value !== null;
                        if ($value !== null) {
                            foreach (ValorAtributoProducto::VALUE_COLUMNS as $column) {
                                $row->$column = null;
                            }
                            $column = ValorAtributoProducto::columnaParaTipo($definition->atributo->tipo_dato);
                            $row->$column = $value;
                        }
                        if (! $row->exists || $row->isDirty()) {
                            $row->modificado_por = $operator->id;
                            $row->save();
                            $this->history->record($row, $operator);
                            $changed = true;
                        }
                    }
                }
                if (! $target && ! empty($data['location_ids'])) {
                    foreach ($data['location_ids'] as $id) {
                        app(GuardarUbicacionProducto::class)->handle($operator, $producto, null, ['ubicacion_id' => $id, 'activo' => true, 'version_bloqueo' => $producto->fresh()->version_bloqueo]);
                    }
                    $producto->refresh();
                } elseif ($target && ! empty($data['location_ids'])) {
                    throw ValidationException::withMessages(['location_ids' => 'Gestiona las ubicaciones desde las asociaciones del producto.']);
                }
                if ($target && $changed) {
                    $producto->version_bloqueo++;
                    $producto->modificado_por = $operator->id;
                    $producto->save();
                }

                return $producto;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['codigo_interno' => 'El código interno ya está registrado, incluso si el producto está inactivo.']);
        }
    }
}
