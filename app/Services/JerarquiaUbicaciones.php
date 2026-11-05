<?php

namespace App\Services;

use App\Models\Ubicacion;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class JerarquiaUbicaciones
{
    // Call only after the ModificacionSegura mutex. Locking reads must not reuse
    // an earlier REPEATABLE-READ snapshot or an already loaded Eloquent relation.
    public function validateParent(Ubicacion $ubicacion, bool $requireActive): void
    {
        $seen = [];
        if ($ubicacion->exists) {
            $seen[$ubicacion->id] = true;
        }
        $id = $ubicacion->padre_id;
        while ($id !== null) {
            if (isset($seen[$id])) {
                throw ValidationException::withMessages(['padre_id' => 'La ubicación no puede ser su propio padre ni depender de un descendiente.']);
            }
            $seen[$id] = true;
            $padre = Ubicacion::whereKey($id)->lockForUpdate()->first();
            if (! $padre || ($requireActive && ! $padre->activo)) {
                throw ValidationException::withMessages(['padre_id' => 'Selecciona un padre existente con todos sus antecesores activos.']);
            }
            $id = $padre->padre_id;
        }
    }

    public function subtreeIds(int $id, bool $lock = false): array
    {
        $seen = [$id => true];
        $frontier = [$id];
        while ($frontier) {
            $query = Ubicacion::whereIn('padre_id', $frontier)->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            $frontier = [];
            foreach ($query->get(['id', 'padre_id']) as $child) {
                if (isset($seen[$child->id])) {
                    throw ValidationException::withMessages(['padre_id' => 'La jerarquía existente contiene un ciclo. Revisa su integridad antes de continuar.']);
                }
                $seen[$child->id] = true;
                $frontier[] = $child->id;
            }
        }

        return array_keys($seen);
    }

    public function catalog(): Collection
    {
        return Ubicacion::orderBy('codigo')->orderBy('id')->get(['id', 'padre_id', 'codigo', 'nombre', 'tipo', 'permite_almacenar', 'activo']);
    }

    // Memoize paths while traversing one consistent query resultado; never recurse
    // through Eloquent relations, which causes N+1 queries and unsafe cycles.
    public function paths(Collection $ubicaciones): array
    {
        $nodes = $ubicaciones->keyBy('id');
        $paths = [];
        foreach ($ubicaciones as $ubicacion) {
            $chain = [];
            $visited = [];
            $cursor = $ubicacion;
            while ($cursor && ! isset($paths[$cursor->id])) {
                if (isset($visited[$cursor->id])) {
                    throw ValidationException::withMessages(['padre_id' => 'La jerarquía contiene un ciclo y no puede mostrarse.']);
                }
                $visited[$cursor->id] = true;
                $chain[] = $cursor;
                if ($cursor->padre_id !== null && ! $nodes->has($cursor->padre_id)) {
                    throw ValidationException::withMessages(['padre_id' => 'La jerarquía contiene un padre inexistente.']);
                }
                $cursor = $cursor->padre_id !== null ? $nodes->get($cursor->padre_id) : null;
            }
            $prefix = $cursor ? $paths[$cursor->id] : '';
            foreach (array_reverse($chain) as $node) {
                $prefix = ($prefix !== '' ? $prefix.' → ' : '').$node->codigo.' · '.$node->nombre;
                $paths[$node->id] = $prefix;
            }
        }

        return $paths;
    }

    public function treeRows(Collection $ubicaciones): array
    {
        $this->paths($ubicaciones); // Detect orphaned/cyclic components before rendering.
        $groups = $ubicaciones->groupBy(fn ($node) => $node->padre_id ?? 0);
        $stack = [];
        foreach ($groups->get(0, collect())->reverse() as $root) {
            $stack[] = [$root, 0];
        }
        $rows = [];
        while ($stack) {
            [$node, $depth] = array_pop($stack);
            $rows[] = ['ubicacion' => $node, 'depth' => $depth];
            foreach ($groups->get($node->id, collect())->reverse() as $child) {
                $stack[] = [$child, $depth + 1];
            }
        }

        return $rows;
    }
}
