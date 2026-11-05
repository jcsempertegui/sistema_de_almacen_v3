<?php

namespace App\Http\Requests;

use App\Models\Rol;
use App\Support\CatalogoPermisos;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudRol extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        $rol = $this->route('rol');

        return $rol
            ? $this->user()->can('update', $rol) || $this->user()->can('assignPermissions', $rol) || $this->user()->can('changeStatus', $rol)
            : $this->user()->can('create', Rol::class);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->route('rol') && is_string($this->input('codigo'))) {
            $this->merge(['codigo' => Str::lower(trim($this->input('codigo')))]);
        }
        if ($this->has('permissions_selection')) {
            $this->merge(['permisos' => $this->input('permisos', [])]);
        }
    }

    public function rules(): array
    {
        $creating = ! $this->route('rol');

        return [
            'codigo' => $creating ? ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'codigo')] : ['prohibited'],
            'nombre' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activo' => [$creating ? 'required' : 'sometimes', 'boolean'],
            'permisos' => ['sometimes', 'array'],
            'permisos.*' => ['required', 'integer', 'distinct', Rule::exists('permisos', 'id')->whereIn('codigo', array_keys(CatalogoPermisos::LABELS))],
        ];
    }

    public function attributes(): array
    {
        return array_merge(parent::attributes(), ['codigo' => 'código', 'descripcion' => 'descripción', 'permisos' => 'permisos', 'permisos.*' => 'permiso']);
    }
}
