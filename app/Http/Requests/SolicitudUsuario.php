<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SolicitudUsuario extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        $target = $this->route('usuario');

        return $target ? $this->user()->can('update', $target) : $this->user()->can('create', Usuario::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => Str::lower(trim($this->input('correo')))]);
        }
    }

    public static function passwordRules(): array
    {
        return ['string', 'min:12', 'max:255', 'confirmed', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).+$/s'];
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'string', 'email', 'max:255', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'contrasena' => [$this->route('usuario') ? 'nullable' : 'required', ...self::passwordRules()],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('activo', true)->orWhere('id', $this->route('usuario')?->rol_id ?? 0))],
            'activo' => ['required', 'boolean'],
        ];
    }
}
