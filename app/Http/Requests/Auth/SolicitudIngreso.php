<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\SolicitudFormularioSga;
use App\Models\Usuario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SolicitudIngreso extends SolicitudFormularioSga
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('correo'))) {
            $this->merge(['correo' => Str::lower(trim($this->input('correo')))]);
        }
    }

    public function rules(): array
    {
        return ['correo' => ['required', 'string', 'email', 'max:255'], 'contrasena' => ['required', 'string', 'max:255']];
    }

    public function authenticate(): void
    {
        $key = hash('sha256', $this->string('correo').'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['correo' => 'Demasiados intentos. Intenta nuevamente en '.RateLimiter::availableIn($key).' segundos.']);
        }
        if (! Auth::attemptWhen(['correo' => $this->input('correo'), 'password' => $this->input('contrasena'), 'activo' => true], fn (Usuario $usuario) => (bool) $usuario->rol?->activo)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['correo' => 'Las credenciales no son válidas o la cuenta está desactivada.']);
        }
        RateLimiter::clear($key);
    }
}
