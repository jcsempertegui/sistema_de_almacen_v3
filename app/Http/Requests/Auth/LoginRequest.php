<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\SgaFormRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends SgaFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']];
    }

    public function authenticate(): void
    {
        $key = hash('sha256', $this->string('email').'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Intenta nuevamente en '.RateLimiter::availableIn($key).' segundos.']);
        }
        if (! Auth::attemptWhen([...$this->only('email', 'password'), 'is_active' => true], fn (User $user) => (bool) $user->role?->is_active)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'Las credenciales no son válidas o la cuenta está desactivada.']);
        }
        RateLimiter::clear($key);
    }
}
