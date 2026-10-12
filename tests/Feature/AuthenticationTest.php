<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_guests_see_login_and_cannot_open_the_panel(): void
    {
        $this->get('/login')->assertOk()->assertSee('Iniciar sesión')->assertDontSee('@vite');
        $this->get('/panel')->assertRedirect(route('login'));
        $this->get('/register')->assertNotFound();
    }

    public function test_valid_credentials_regenerate_the_session_and_open_the_panel(): void
    {
        $user = User::factory()->create(['email' => 'operador@example.com']);
        $this->withSession(['marker' => 'preserved']);
        $previousId = session()->getId();
        $this->post('/login', ['email' => ' OPERADOR@EXAMPLE.COM ', 'password' => 'password'])
            ->assertRedirect(route('dashboard'))->assertSessionHas('marker', 'preserved')->assertSessionHas('auth_version', 1);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($previousId, session()->getId());
        $this->get('/panel')->assertOk();
    }

    public function test_invalid_credentials_and_inactive_accounts_are_rejected(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        foreach (['wrong', 'password'] as $password) {
            $this->post('/login', ['email' => $user->email, 'password' => $password])->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    public function test_login_validates_input_and_never_flashes_passwords(): void
    {
        $this->post('/login', ['email' => 'invalid', 'password' => 'sensitive'])->assertSessionHasErrors('email')->assertSessionMissing('_old_input.password');
    }

    public function test_login_is_limited_by_normalized_email_and_ip(): void
    {
        $user = User::factory()->create();
        $key = hash('sha256', $user->email.'|127.0.0.1');
        RateLimiter::clear($key);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->signIn($user)->withSession(['marker' => 'removed']);
        $previousToken = session()->token();
        $this->post('/logout')->assertRedirect(route('login'))->assertSessionMissing('marker');
        $this->assertGuest();
        $this->assertNotSame($previousToken, session()->token());
        $this->get('/logout')->assertStatus(405);
    }

    public function test_inactive_or_revoked_sessions_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        $user->forceFill(['is_active' => false])->save();
        $this->get('/panel')->assertRedirect(route('login'));
        $this->assertGuest();
        $user->forceFill(['is_active' => true])->save();
        $this->signIn($user);
        $user->increment('session_version');
        $this->getJson('/panel')->assertUnauthorized();
        $this->assertGuest();
    }

    public function test_login_requires_a_csrf_token(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'test@example.com', 'password' => 'password'])->assertStatus(419);
    }
}
