<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role
            ? $this->user()->can('update', $role) || $this->user()->can('assignPermissions', $role) || $this->user()->can('changeStatus', $role)
            : $this->user()->can('create', Role::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->route('role') && is_string($this->input('code'))) {
            $this->merge(['code' => Str::lower(trim($this->input('code')))]);
        }
        if ($this->has('permissions_selection')) {
            $this->merge(['permissions' => $this->input('permissions', [])]);
        }
    }

    public function rules(): array
    {
        $creating = ! $this->route('role');

        return [
            'code' => $creating ? ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'code')] : ['prohibited'],
            'name' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => [$creating ? 'required' : 'sometimes', 'boolean'],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['required', 'integer', 'distinct', Rule::exists('permissions', 'id')->whereIn('code', array_keys(PermissionCatalog::LABELS))],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['code' => 'código', 'description' => 'descripción', 'permissions' => 'permisos', 'permissions.*' => 'permiso']);
    }
}
