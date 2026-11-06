<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_guests_see_login_and_cannot_open_the_panel(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Iniciar sesión')->assertDontSee('@vite');
        $this->get('/panel')->assertRedirect(route('login'));
        $this->get('/register')->assertNotFound();
    }

    public function test_valid_credentials_regenerate_the_session_and_open_the_panel(): void
    {
        $usuario = Usuario::factory()->create(['correo' => 'operador@example.com']);
        $this->withSession(['marker' => 'preserved']);
        $previousId = session()->getId();
        $this->post('/login', ['correo' => ' OPERADOR@EXAMPLE.COM ', 'contrasena' => 'contrasena'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('marker', 'preserved')->assertSessionHas('auth_version', 1);
        $this->assertAuthenticatedAs($usuario);
        $this->assertNotSame($previousId, session()->getId());
        $this->get('/panel')->assertOk();
    }

    public function test_invalid_credentials_and_inactive_accounts_are_rejected(): void
    {
        $usuario = Usuario::factory()->create(['activo' => false]);
        foreach (['wrong', 'contrasena'] as $contrasena) {
            $this->post('/login', ['correo' => $usuario->correo, 'contrasena' => $contrasena])->assertSessionHasErrors('correo');
            $this->assertGuest();
        }
    }

    public function test_login_validates_input_and_never_flashes_passwords(): void
    {
        $this->post('/login', ['correo' => 'invalid', 'contrasena' => 'sensitive'])->assertSessionHasErrors('correo')->assertSessionMissing('_old_input.contrasena');
    }

    public function test_login_is_limited_by_normalized_email_and_ip(): void
    {
        $usuario = Usuario::factory()->create();
        $key = hash('sha256', $usuario->correo.'|127.0.0.1');
        RateLimiter::clear($key);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['correo' => $usuario->correo, 'contrasena' => 'wrong'])->assertSessionHasErrors('correo');
        }
        $this->post('/login', ['correo' => strtoupper($usuario->correo), 'contrasena' => 'contrasena'])->assertSessionHasErrors('correo');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['correo' => $usuario->correo, 'contrasena' => 'contrasena'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $usuario = Usuario::factory()->create();
        $this->signIn($usuario)->withSession(['marker' => 'removed']);
        $previousToken = session()->token();
        $this->post('/logout')->assertRedirect(route('login'))->assertSessionMissing('marker');
        $this->assertGuest();
        $this->assertNotSame($previousToken, session()->token());
        $this->get(route('logout'))->assertStatus(405);
    }

    public function test_inactive_or_revoked_sessions_are_rejected(): void
    {
        $usuario = Usuario::factory()->create();
        $this->signIn($usuario);
        $usuario->forceFill(['activo' => false])->save();
        $this->get('/panel')->assertRedirect(route('login'));
        $this->assertGuest();
        $usuario->forceFill(['activo' => true])->save();
        $this->signIn($usuario);
        $usuario->increment('version_sesion');
        $this->getJson('/panel')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_login_requires_a_csrf_token(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['correo' => 'test@example.com', 'contrasena' => 'contrasena'])->assertStatus(419);
    }
}
