<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarCategoria;
use App\Actions\Catalogo\GuardarUnidadMedida;
use App\Actions\Roles\GuardarRol;
use App\Models\Categoria;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class CatalogAuthorizationTest extends TestCase
{
    private function resources(): array
    {
        $categoria = Categoria::factory()->create();
        $unidad = UnidadMedida::factory()->create();

        return [
            ['categories', 'categorias', $categoria, $categoria->only(['codigo', 'nombre', 'descripcion', 'activo'])],
            ['measurement_units', 'unidades-medida', $unidad, $unidad->only(['codigo', 'nombre', 'simbolo', 'dimension', 'decimales', 'activo'])],
        ];
    }

    private function customUser(array $codes): Usuario
    {
        $rol = Rol::create(['codigo' => 'custom_'.bin2hex(random_bytes(4)), 'nombre' => 'Administrador']);
        $rol->permisos()->attach(Permiso::whereIn('codigo', $codes)->pluck('id')->all(), ['activo' => true]);

        return Usuario::factory()->create(['rol_id' => $rol->id]);
    }

    public function test_auditor_has_read_access_but_all_direct_mutations_are_forbidden(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        foreach ($this->resources() as [$permiso, $route, $target, $data]) {
            $this->get(route($route.'.index'))->assertOk()->assertDontSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.create'))->assertForbidden();
            $this->get(route($route.'.edit', $target))->assertForbidden();
            $this->post(route($route.'.store'), $data)->assertForbidden();
            $this->put(route($route.'.update', $target), $data)->assertForbidden();
            $this->patch(route($route.'.estado', $target), ['activo' => 0])->assertForbidden();
            $this->assertTrue($target->fresh()->activo);
        }
    }

    public function test_guests_and_inactive_accounts_cannot_access_the_catalog(): void
    {
        foreach ($this->resources() as [$permiso, $route, $target, $data]) {
            $this->get(route($route.'.index'))->assertRedirect(route('login'));
            $this->post(route($route.'.store'), $data)->assertRedirect(route('login'));
        }
        $inactive = Usuario::factory()->create(['rol_id' => 1, 'activo' => false]);
        foreach (['categorias.index', 'unidades-medida.index'] as $route) {
            $this->signIn($inactive)->getJson(route($route))->assertUnauthorized();
        }
    }

    public function test_permissions_control_independent_actions_and_role_names_do_not_grant_access(): void
    {
        foreach ($this->resources() as [$permiso, $route, $target, $data]) {
            $actor = $this->customUser([$permiso.'.view', $permiso.'.update']);
            $this->signIn($actor)->get(route($route.'.index'))->assertOk()->assertSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.edit', $target))->assertOk();
            $this->put(route($route.'.update', $target), array_replace($data, ['nombre' => 'Edición permitida']))->assertRedirect();
            $this->post(route($route.'.store'), $data)->assertForbidden();
            $this->patch(route($route.'.estado', $target), ['activo' => 0])->assertForbidden();
            $this->put(route($route.'.update', $target), array_replace($data, ['activo' => 0]))->assertForbidden();
            $this->assertTrue($target->fresh()->activo);
        }
        $this->signIn($this->customUser([]))->get(route('categorias.index'))->assertForbidden();
        $this->get(route('unidades-medida.index'))->assertForbidden();
    }

    public function test_status_permission_does_not_allow_editing_other_fields(): void
    {
        foreach ($this->resources() as [$permiso, $route, $target, $data]) {
            $this->signIn($this->customUser([$permiso.'.view', $permiso.'.change_status']));
            $this->patch(route($route.'.estado', $target), ['activo' => 0, 'nombre' => 'Manipulado'])->assertRedirect();
            $this->assertSame($data['nombre'], $target->fresh()->nombre);
            $this->put(route($route.'.update', $target), $data)->assertForbidden();
            $this->get(route($route.'.edit', $target))->assertForbidden();
        }
    }

    public function test_revocation_invalidates_session_and_rejects_catalog_writes_in_flight(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $actor->load('rol');
        $this->signIn($actor);
        $categoria = Categoria::factory()->create();
        $unidad = UnidadMedida::factory()->create();
        $codes = array_diff($actor->rol->codigosPermisosAsignados(), ['categories.change_status', 'measurement_units.change_status']);
        app(GuardarRol::class)->handle($admin, $actor->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->patchJson(route('categorias.estado', $categoria), ['activo' => 0])->assertUnauthorized();
        foreach ([fn () => app(GuardarCategoria::class)->handle($actor, $categoria, ['activo' => false], statusOnly: true), fn () => app(GuardarUnidadMedida::class)->handle($actor, $unidad, ['activo' => false], statusOnly: true)] as $operation) {
            try {
                $operation();
                $this->fail('La escritura con acceso revocado debe rechazarse.');
            } catch (AuthorizationException) {
                $this->assertTrue($categoria->fresh()->activo);
                $this->assertTrue($unidad->fresh()->activo);
            }
        }
        $this->signIn($actor->fresh())->patch(route('unidades-medida.estado', $unidad), ['activo' => 0])->assertForbidden();
    }

    public function test_catalog_post_requires_csrf(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $this->app->instance('env', 'local');
        $this->post(route('categorias.store'), ['codigo' => 'SIN-CSRF', 'nombre' => 'Sin token', 'activo' => 1])->assertStatus(419);
        $this->post(route('unidades-medida.store'), ['codigo' => 'SIN-CSRF', 'nombre' => 'Sin token', 'simbolo' => 'x', 'dimension' => 'count', 'decimales' => 0, 'activo' => 1])->assertStatus(419);
    }

    public function test_permissions_are_visible_and_editable_through_existing_role_management(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $target = $this->customUser([]);
        $this->signIn($actor)->get(route('roles.edit', $target->rol))->assertOk()->assertSee('Categorías')->assertSee('Unidades de medida');
        $this->put(route('roles.update', $target->rol), ['permisos' => Permiso::whereIn('codigo', ['panel.view', 'categories.view', 'measurement_units.create'])->pluck('id')->all()])->assertRedirect();
        $this->signIn($target->fresh())->get(route('categorias.index'))->assertOk();
        $this->get(route('unidades-medida.create'))->assertOk();
        $this->get(route('unidades-medida.index'))->assertForbidden();
    }
}
