<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\TestCase;

class ExpiredRequestPageTest extends TestCase
{
    public function test_guest_csrf_failure_uses_the_spanish_page_and_keeps_status_419(): void
    {
        $this->app->instance('env', 'local');

        $this->post('/login', ['correo' => 'expired@example.test', 'contrasena' => 'NotAValidPassword123!'])
            ->assertStatus(419)
            ->assertSee('Solicitud no válida o caducada')
            ->assertSee('No se pudo validar la solicitud.')
            ->assertSee('Volver al inicio')
            ->assertSee('href="'.url('/').'"', escape: false)
            ->assertSee('lang="es"', escape: false)
            ->assertSee('/css/sga.css')
            ->assertDontSee('Page Expired')
            ->assertDontSee('CSRF token mismatch');

        $this->assertGuest();
    }

    public function test_authenticated_invalid_token_cannot_create_a_category(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->app->instance('env', 'local');

        $this->post(route('categorias.store'), [
            '_token' => 'invalid-token', 'codigo' => 'EXPIRED-REQUEST', 'nombre' => 'Solicitud rechazada', 'activo' => 1,
        ])->assertStatus(419)->assertSee('Solicitud no válida o caducada')->assertSee('Volver al inicio');

        $this->assertDatabaseMissing('categorias', ['codigo' => 'EXPIRED-REQUEST']);
    }

    public function test_json_csrf_failure_keeps_the_native_json_response(): void
    {
        $this->app->instance('env', 'local');

        $this->postJson('/login', ['correo' => 'expired@example.test', 'contrasena' => 'NotAValidPassword123!'])
            ->assertStatus(419)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('message', 'CSRF token mismatch.')
            ->assertDontSee('<!DOCTYPE html>', escape: false);
    }

    public function test_valid_csrf_token_still_allows_a_normal_login(): void
    {
        $usuario = Usuario::factory()->create();
        $this->withSession(['_token' => 'valid-test-token']);
        $this->app->instance('env', 'local');

        $this->post('/login', ['_token' => 'valid-test-token', 'correo' => $usuario->correo, 'contrasena' => 'contrasena'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }
}
