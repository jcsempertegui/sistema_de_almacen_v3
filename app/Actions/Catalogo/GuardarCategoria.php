<?php

namespace App\Actions\Catalogo;

use App\Models\Categoria;
use App\Models\Usuario;
use App\Services\ModificacionSegura;
use App\Services\ReferenciasCatalogo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class GuardarCategoria
{
    public function __construct(private ModificacionSegura $security, private ReferenciasCatalogo $references) {}

    public function handle(Usuario $actor, ?Categoria $target, array $data, bool $statusOnly = false): Categoria
    {
        try {
            return $this->security->run($actor, function (Usuario $operator) use ($target, $data, $statusOnly) {
                $categoria = $target ? Categoria::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Categoria;
                $this->security->exigirPermisos($operator, ! $target ? 'categories.create' : ($statusOnly ? 'categories.change_status' : 'categories.update'));
                $categoria->fill(array_intersect_key($data, array_flip($statusOnly ? ['activo'] : ['codigo', 'nombre', 'descripcion', 'activo'])));
                if ($target && $categoria->isDirty('activo')) {
                    $this->security->exigirPermisos($operator, 'categories.change_status');
                    if (! $categoria->activo && $this->references->categoryHasActiveProducts($categoria->id)) {
                        throw ValidationException::withMessages(['activo' => 'No puedes desactivar una categoría con productos activos asociados.']);
                    }
                }
                if (! $target) {
                    $categoria->creado_por = $operator->id;
                }
                if (! $categoria->exists || $categoria->isDirty()) {
                    $categoria->modificado_por = $operator->id;
                    $categoria->save();
                }

                return $categoria;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            $query = Categoria::where('codigo', $data['codigo'] ?? $target?->codigo)->when($target, fn ($query) => $query->whereKeyNot($target->id));
            throw ValidationException::withMessages([$query->exists() ? 'codigo' : 'nombre' => 'El código o nombre de categoría ya está registrado.']);
        }
    }
}
