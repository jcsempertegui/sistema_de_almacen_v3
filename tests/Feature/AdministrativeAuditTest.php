<?php

namespace Tests\Feature;

use App\Actions\Usuarios\ActualizarUsuario;
use App\Actions\Usuarios\CrearUsuario;
use App\Models\RegistroAuditoriaAdministrativa;
use App\Models\Usuario;
use Tests\TestCase;

class AdministrativeAuditTest extends TestCase
{
    public function test_user_audit_captures_actor_before_after_and_password_flag_without_secrets(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $target = app(CrearUsuario::class)->handle($actor, ['nombre' => '<script>Cuenta</script>', 'correo' => 'auditoria@example.com', 'contrasena' => 'ClaveSegura2026!', 'rol_id' => 3, 'activo' => true]);
        app(ActualizarUsuario::class)->handle($actor, $target, ['contrasena' => 'NuevaClave2026!']);
        $log = RegistroAuditoriaAdministrativa::where('usuario_id', $target->id)->latest('id')->firstOrFail();
        $this->assertSame($actor->id, $log->actor_id);
        $this->assertSame($actor->nombre, $log->nombre_actor);
        $this->assertTrue($log->datos_posteriores['password_changed']);
        $encoded = json_encode($log->toArray());
        foreach (['ClaveSegura2026!', 'NuevaClave2026!', $target->fresh()->contrasena, 'token_recuerdo', 'APP_KEY'] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
        }
        $this->signIn($actor)->get(route('audit.index', ['q' => 'auditoria@example.com']))->assertOk();
        $this->get(route('audit.index', ['q' => 'Cuenta']))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>Cuenta</script>', false);
    }

    public function test_audit_access_is_read_only_and_permission_controlled(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]))->get(route('audit.index', ['q' => 'rbac.initialized']))->assertOk()->assertSee('Permisos iniciales');
        $this->post(route('audit.index'), [])->assertStatus(405);
        $this->delete(route('audit.index'))->assertStatus(405);
        $this->signIn(Usuario::factory()->create(['rol_id' => 2]))->get(route('audit.index'))->assertForbidden();
    }

    public function test_audit_model_cannot_be_updated(): void
    {
        $log = RegistroAuditoriaAdministrativa::firstOrFail();
        $this->expectException(\LogicException::class);
        $log->update(['accion' => 'tampered']);
    }

    public function test_audit_model_cannot_be_deleted(): void
    {
        $this->expectException(\LogicException::class);
        RegistroAuditoriaAdministrativa::firstOrFail()->delete();
    }
}
