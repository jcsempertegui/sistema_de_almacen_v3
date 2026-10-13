<?php

namespace Tests\Feature;

use App\Actions\Users\CreateUser;
use App\Actions\Users\UpdateUser;
use App\Models\AdministrativeAuditLog;
use App\Models\User;
use Tests\TestCase;

class AdministrativeAuditTest extends TestCase
{
    public function test_user_audit_captures_actor_before_after_and_password_flag_without_secrets(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $target = app(CreateUser::class)->handle($actor, ['name' => '<script>Cuenta</script>', 'email' => 'auditoria@example.com', 'password' => 'ClaveSegura2026!', 'role_id' => 3, 'is_active' => true]);
        app(UpdateUser::class)->handle($actor, $target, ['password' => 'NuevaClave2026!']);
        $log = AdministrativeAuditLog::where('user_id', $target->id)->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame($actor->name, $log->actor_name);
        $this->assertTrue($log->after_data['password_changed']);
        $encoded = json_encode($log->toArray());
        foreach (['ClaveSegura2026!', 'NuevaClave2026!', $target->fresh()->password, 'remember_token', 'APP_KEY'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->signIn($actor)->get(route('audit.index', ['q' => 'auditoria@example.com']))->assertOk();
        $this->get(route('audit.index', ['q' => 'Cuenta']))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>Cuenta</script>', false);
    }

    public function test_audit_access_is_read_only_and_permission_controlled(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 3]))->get(route('audit.index'))->assertOk()->assertSee('Permisos iniciales');
        $this->post(route('audit.index'), [])->assertStatus(405);
        $this->delete(route('audit.index'))->assertStatus(405);
        $this->signIn(User::factory()->create(['role_id' => 2]))->get(route('audit.index'))->assertForbidden();
    }

    public function test_audit_model_cannot_be_updated(): void
    {
        $log = AdministrativeAuditLog::firstOrFail();
        $this->expectException(\LogicException::class);
        $log->update(['action' => 'tampered']);
    }

    public function test_audit_model_cannot_be_deleted(): void
    {
        $this->expectException(\LogicException::class);
        AdministrativeAuditLog::firstOrFail()->delete();
    }
}
