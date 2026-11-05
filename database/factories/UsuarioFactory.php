<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /**
     * The current contrasena being used by the factory.
     */
    protected static ?string $contrasena;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'fecha_verificacion_correo' => now(),
            'contrasena' => static::$contrasena ??= Hash::make('contrasena'),
            'token_recuerdo' => Str::random(10),
            'activo' => true,
            'version_sesion' => 1,
            'rol_id' => fn () => Rol::where('codigo', Rol::AUDITOR)->value('id'),
        ];
    }

    /**
     * Indicate that the model's correo address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $atributos) => [
            'fecha_verificacion_correo' => null,
        ]);
    }
}
