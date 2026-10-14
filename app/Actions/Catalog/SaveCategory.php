<?php

namespace App\Actions\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\CatalogReferences;
use App\Services\SecurityMutation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SaveCategory
{
    public function __construct(private SecurityMutation $security, private CatalogReferences $references) {}

    public function handle(User $actor, ?Category $target, array $data, bool $statusOnly = false): Category
    {
        try {
            return $this->security->run($actor, function (User $operator) use ($target, $data, $statusOnly) {
                $category = $target ? Category::whereKey($target->id)->lockForUpdate()->firstOrFail() : new Category;
                $this->security->requirePermissions($operator, ! $target ? 'categories.create' : ($statusOnly ? 'categories.change_status' : 'categories.update'));
                $category->fill(array_intersect_key($data, array_flip($statusOnly ? ['is_active'] : ['code', 'name', 'description', 'is_active'])));
                if ($target && $category->isDirty('is_active')) {
                    $this->security->requirePermissions($operator, 'categories.change_status');
                    if (! $category->is_active && $this->references->categoryHasActiveProducts($category->id)) {
                        throw ValidationException::withMessages(['is_active' => 'No puedes desactivar una categoría con productos activos asociados.']);
                    }
                }
                if (! $target) {
                    $category->created_by = $operator->id;
                }
                if (! $category->exists || $category->isDirty()) {
                    $category->updated_by = $operator->id;
                    $category->save();
                }

                return $category;
            }, protectAdministrativeAccess: false);
        } catch (UniqueConstraintViolationException) {
            $query = Category::where('code', $data['code'] ?? $target?->code)->when($target, fn ($query) => $query->whereKeyNot($target->id));
            throw ValidationException::withMessages([$query->exists() ? 'code' : 'name' => 'El código o nombre de categoría ya está registrado.']);
        }
    }
}
