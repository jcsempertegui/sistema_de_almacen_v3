<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class LocationHierarchy
{
    // Call only after the SecurityMutation mutex. Locking reads must not reuse
    // an earlier REPEATABLE-READ snapshot or an already loaded Eloquent relation.
    public function validateParent(Location $location, bool $requireActive): void
    {
        $seen = [];
        if ($location->exists) {
            $seen[$location->id] = true;
        }
        $id = $location->parent_id;
        while ($id !== null) {
            if (isset($seen[$id])) {
                throw ValidationException::withMessages(['parent_id' => 'La ubicación no puede ser su propio padre ni depender de un descendiente.']);
            }
            $seen[$id] = true;
            $parent = Location::whereKey($id)->lockForUpdate()->first();
            if (! $parent || ($requireActive && ! $parent->is_active)) {
                throw ValidationException::withMessages(['parent_id' => 'Selecciona un padre existente con todos sus antecesores activos.']);
            }
            $id = $parent->parent_id;
        }
    }

    public function subtreeIds(int $id, bool $lock = false): array
    {
        $seen = [$id => true];
        $frontier = [$id];
        while ($frontier) {
            $query = Location::whereIn('parent_id', $frontier)->orderBy('id');
            if ($lock) {
                $query->lockForUpdate();
            }
            $frontier = [];
            foreach ($query->get(['id', 'parent_id']) as $child) {
                if (isset($seen[$child->id])) {
                    throw ValidationException::withMessages(['parent_id' => 'La jerarquía existente contiene un ciclo. Revisa su integridad antes de continuar.']);
                }
                $seen[$child->id] = true;
                $frontier[] = $child->id;
            }
        }

        return array_keys($seen);
    }

    public function catalog(): Collection
    {
        return Location::orderBy('code')->orderBy('id')->get(['id', 'parent_id', 'code', 'name', 'type', 'is_storable', 'is_active']);
    }

    // Memoize paths while traversing one consistent query result; never recurse
    // through Eloquent relations, which causes N+1 queries and unsafe cycles.
    public function paths(Collection $locations): array
    {
        $nodes = $locations->keyBy('id');
        $paths = [];
        foreach ($locations as $location) {
            $chain = [];
            $visited = [];
            $cursor = $location;
            while ($cursor && ! isset($paths[$cursor->id])) {
                if (isset($visited[$cursor->id])) {
                    throw ValidationException::withMessages(['parent_id' => 'La jerarquía contiene un ciclo y no puede mostrarse.']);
                }
                $visited[$cursor->id] = true;
                $chain[] = $cursor;
                if ($cursor->parent_id !== null && ! $nodes->has($cursor->parent_id)) {
                    throw ValidationException::withMessages(['parent_id' => 'La jerarquía contiene un padre inexistente.']);
                }
                $cursor = $cursor->parent_id !== null ? $nodes->get($cursor->parent_id) : null;
            }
            $prefix = $cursor ? $paths[$cursor->id] : '';
            foreach (array_reverse($chain) as $node) {
                $prefix = ($prefix !== '' ? $prefix.' → ' : '').$node->code.' · '.$node->name;
                $paths[$node->id] = $prefix;
            }
        }

        return $paths;
    }

    public function treeRows(Collection $locations): array
    {
        $this->paths($locations); // Detect orphaned/cyclic components before rendering.
        $groups = $locations->groupBy(fn ($node) => $node->parent_id ?? 0);
        $stack = [];
        foreach ($groups->get(0, collect())->reverse() as $root) {
            $stack[] = [$root, 0];
        }
        $rows = [];
        while ($stack) {
            [$node, $depth] = array_pop($stack);
            $rows[] = ['location' => $node, 'depth' => $depth];
            foreach ($groups->get($node->id, collect())->reverse() as $child) {
                $stack[] = [$child, $depth + 1];
            }
        }

        return $rows;
    }
}
