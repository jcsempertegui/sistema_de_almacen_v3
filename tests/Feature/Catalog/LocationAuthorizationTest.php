<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarUbicacion;
use App\Actions\Roles\GuardarRol;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Ubicacion;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class LocationAuthorizationTest extends TestCase
{
    private function customActor(array $codes): Usuario
    {
        $rol = Rol::create(['codigo' => 'loc_'.bin2hex(random_bytes(4)), 'nombre' => 'Administrador']);
        $rol->permisos()->attach(Permiso::whereIn('codigo', $codes)->pluck('id')->all(), ['activo' => true]);

        return Usuario::factory()->create(['rol_id' => $rol->id]);
    }

    private function data(Ubicacion $node): array
    {
        return $node->only(['codigo', 'nombre', 'tipo', 'padre_id', 'permite_almacenar', 'descripcion', 'activo']);
    }

    public function test_auditor_can_read_index_and_tree_but_all_direct_mutations_are_forbidden(): void
    {
        $node = Ubicacion::factory()->create();
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        $this->get(route('ubicaciones.index'))->assertOk()->assertDontSee('Editar')->assertDontSee('Desactivar')->assertDontSee('Nueva ubicación');
        $this->get(route('ubicaciones.tree'))->assertOk();
        $this->get(route('ubicaciones.create'))->assertForbidden();
        $this->get(route('ubicaciones.edit', $node))->assertForbidden();
        $this->post(route('ubicaciones.store'), $this->data($node))->assertForbidden();
        $this->put(route('ubicaciones.update', $node), $this->data($node))->assertForbidden();
        $this->patch(route('ubicaciones.estado', $node), ['activo' => 0])->assertForbidden();
        $this->assertTrue($node->fresh()->activo);
    }

    public function test_guests_and_inactive_users_cannot_access_locations(): void
    {
        $node = Ubicacion::factory()->create();
        $this->get(route('ubicaciones.index'))->assertRedirect(route('login'));
        $this->get(route('ubicaciones.tree'))->assertRedirect(route('login'));
        $this->post(route('ubicaciones.store'), $this->data($node))->assertRedirect(route('login'));
        $inactive = Usuario::factory()->create(['rol_id' => 1, 'activo' => false]);
        $this->signIn($inactive)->getJson(route('ubicaciones.index'))->assertUnauthorized();
    }

    public function test_each_action_requires_its_permission_even_with_an_administrator_role_name(): void
    {
        $node = Ubicacion::factory()->create();
        $data = $this->data($node);
        $this->signIn($this->customActor(['locations.view', 'locations.update']));
        $this->get(route('ubicaciones.index'))->assertOk()->assertSee('Editar')->assertDontSee('Desactivar');
        $this->get(route('ubicaciones.edit', $node))->assertOk();
        $this->put(route('ubicaciones.update', $node), array_replace($data, ['nombre' => 'Corrección permitida']))->assertSessionHasNoErrors();
        $this->post(route('ubicaciones.store'), $data)->assertForbidden();
        $this->patch(route('ubicaciones.estado', $node), ['activo' => 0])->assertForbidden();
        $this->put(route('ubicaciones.update', $node), array_replace($data, ['activo' => 0]))->assertForbidden();
        $this->signIn($this->customActor([]))->get(route('ubicaciones.index'))->assertForbidden();
        $this->get(route('ubicaciones.index', ['padre_id' => 999999, 'q' => str_repeat('x', 101)]))->assertForbidden();
        $this->get(route('ubicaciones.tree'))->assertForbidden();
        $this->assertTrue($node->fresh()->activo);
    }

    public function test_create_status_and_metadata_permissions_are_independent(): void
    {
        $node = Ubicacion::factory()->create();
        $this->signIn($this->customActor(['locations.create', 'locations.change_status']));
        $this->get(route('ubicaciones.create'))->assertOk();
        $this->post(route('ubicaciones.store'), array_replace($this->data($node), ['codigo' => 'NUEVA']))->assertSessionHasNoErrors();
        $this->get(route('ubicaciones.edit', $node))->assertForbidden();
        $this->put(route('ubicaciones.update', $node), $this->data($node))->assertForbidden();
        $this->patch(route('ubicaciones.estado', $node), ['activo' => 0, 'nombre' => 'Manipulado', 'padre_id' => 999999, 'permite_almacenar' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($node->fresh()->activo);
        $this->assertSame($node->nombre, $node->fresh()->nombre);
        $this->assertNull($node->fresh()->padre_id);
        $this->assertTrue($node->fresh()->permite_almacenar);
    }

    public function test_revocation_invalidates_sessions_and_rejects_an_in_flight_action(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $actor->load('rol');
        $node = Ubicacion::factory()->create();
        $this->signIn($actor);
        $codes = array_diff($actor->rol->codigosPermisosAsignados(), ['locations.update']);
        app(GuardarRol::class)->handle($admin, $actor->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->putJson(route('ubicaciones.update', $node), $this->data($node))->assertUnauthorized();
        try {
            app(GuardarUbicacion::class)->handle($actor, $node, ['nombre' => 'No persistir']);
            $this->fail('El cambio de acceso debe invalidar el operador anterior.');
        } catch (AuthorizationException) {
            $this->assertNotSame('No persistir', $node->fresh()->nombre);
        }
        $this->signIn($actor->fresh())->put(route('ubicaciones.update', $node), $this->data($node))->assertForbidden();
    }

    public function test_forms_require_csrf_and_new_permissions_are_configurable_with_existing_roles(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $actor = $this->customActor([]);
        $this->signIn($admin)->get(route('roles.edit', $actor->rol))->assertOk()->assertSee('Ubicaciones')->assertSee('locations.change_status');
        $this->put(route('roles.update', $actor->rol), ['permisos' => Permiso::whereIn('codigo', ['panel.view', 'locations.view'])->pluck('id')->all()])->assertRedirect();
        $this->signIn($actor->fresh())->get(route('dashboard'))->assertSee('Consultar ubicaciones');
        $this->get(route('ubicaciones.tree'))->assertOk();
        $this->get(route('ubicaciones.create'))->assertForbidden();
        $this->signIn($admin);
        $this->app->instance('env', 'local');
        $this->post(route('ubicaciones.store'), ['codigo' => 'SIN-CSRF', 'nombre' => 'Sin token', 'tipo' => 'sector', 'permite_almacenar' => 1, 'activo' => 1])->assertStatus(419);
    }

    public function test_editor_preserves_creator_and_server_sets_updated_by(): void
    {
        $creador = Usuario::factory()->create(['rol_id' => 1]);
        $editor = Usuario::factory()->create(['rol_id' => 2]);
        $node = Ubicacion::factory()->create(['creado_por' => $creador->id, 'modificado_por' => $creador->id]);
        $this->signIn($editor)->put(route('ubicaciones.update', $node), array_replace($this->data($node), ['nombre' => 'Corrección', 'creado_por' => $editor->id, 'modificado_por' => 999999]))->assertSessionHasNoErrors();
        $this->assertSame($creador->id, $node->fresh()->creado_por);
        $this->assertSame($editor->id, $node->fresh()->modificado_por);
    }
}
