<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarAtributoCatalogo;
use App\Actions\Catalogo\GuardarAtributoCategoria;
use App\Actions\Roles\GuardarRol;
use App\Models\Categoria;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class TechnicalAttributeAuthorizationTest extends TestCase
{
    use CreatesTechnicalCatalog;

    private function resources(): array
    {
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $association = $this->association($categoria, $atributo, ['activo' => false]);

        return [
            ['attributes', 'atributos', $atributo, $atributo, [], $this->attributeData(['codigo' => 'NEW']), $this->attributeData(['codigo' => $atributo->codigo])],
            ['category_attributes', 'atributos-categoria', [$categoria, $association], $association, [$categoria], $this->associationData(['atributo_id' => $this->technicalAttribute()->id]), $this->associationData(['activo' => 0])],
        ];
    }

    private function customActor(array $codes): Usuario
    {
        $rol = Rol::create(['codigo' => 'technical_'.bin2hex(random_bytes(4)), 'nombre' => 'Administrador']);
        $rol->permisos()->attach(Permiso::whereIn('codigo', $codes)->pluck('id')->all(), ['activo' => true]);

        return Usuario::factory()->create(['rol_id' => $rol->id]);
    }

    public function test_auditor_can_read_but_cannot_mutate_either_entity_through_direct_requests(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 3]));
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $padre, $createData, $editData]) {
            $this->get(route($route.'.index', $padre))->assertOk()->assertDontSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.create', $padre))->assertForbidden();
            $this->get(route($route.'.edit', $parameters))->assertForbidden();
            $this->post(route($route.'.store', $padre), $createData)->assertForbidden();
            $this->put(route($route.'.update', $parameters), $editData)->assertForbidden();
            $this->patch(route($route.'.estado', $parameters), ['activo' => 1])->assertForbidden();
        }
    }

    public function test_guests_and_inactive_accounts_cannot_read_or_write(): void
    {
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $padre, $createData]) {
            $this->get(route($route.'.index', $padre))->assertRedirect(route('login'));
            $this->post(route($route.'.store', $padre), $createData)->assertRedirect(route('login'));
        }
        $inactive = Usuario::factory()->create(['rol_id' => 1, 'activo' => false]);
        $categoria = Categoria::factory()->create();
        foreach ([['atributos.index', []], ['atributos-categoria.index', $categoria]] as [$route, $parameters]) {
            $this->signIn($inactive)->getJson(route($route, $parameters))->assertUnauthorized();
        }
    }

    public function test_custom_permissions_are_independent_and_role_name_does_not_grant_privileges(): void
    {
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $padre, $createData, $editData]) {
            $this->signIn($this->customActor([$prefix.'.view', $prefix.'.update']));
            $this->get(route($route.'.index', $padre))->assertOk()->assertSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.edit', $parameters))->assertOk();
            $this->put(route($route.'.update', $parameters), $editData)->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route($route.'.store', $padre), $createData)->assertForbidden();
            $this->patch(route($route.'.estado', $parameters), ['activo' => 1])->assertForbidden();
            $this->put(route($route.'.update', $parameters), array_replace($editData, ['activo' => ! $target->activo]))->assertForbidden();
            $this->signIn($this->customActor([]))->get(route($route.'.index', $padre))->assertForbidden();
        }
    }

    public function test_create_and_status_permissions_do_not_grant_metadata_editing(): void
    {
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $padre, $createData, $editData]) {
            $this->signIn($this->customActor([$prefix.'.create', $prefix.'.change_status']));
            $this->get(route($route.'.create', $padre))->assertOk();
            $this->post(route($route.'.store', $padre), $createData)->assertRedirect()->assertSessionHasNoErrors();
            $this->put(route($route.'.update', $parameters), $editData)->assertForbidden();
            $this->patch(route($route.'.estado', $parameters), ['activo' => 1, 'nombre' => 'Manipulado', 'orden_presentacion' => 600])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNotSame('Manipulado', $target->fresh()->nombre);
            $this->assertNotSame(600, $target->fresh()->orden_presentacion);
        }
    }

    public function test_revocation_invalidates_sessions_and_rejects_in_flight_mutations_for_both_entities(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $actor = Usuario::factory()->create(['rol_id' => 2]);
        $actor->load('rol');
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $association = $this->association($categoria, $atributo, ['activo' => false]);
        $this->signIn($actor);
        $codes = array_diff($actor->rol->codigosPermisosAsignados(), ['attributes.update', 'category_attributes.update']);
        app(GuardarRol::class)->handle($admin, $actor->rol, ['permisos' => Permiso::whereIn('codigo', $codes)->pluck('id')->all()]);
        $this->putJson(route('atributos.update', $atributo), $this->attributeData())->assertUnauthorized();
        foreach ([fn () => app(GuardarAtributoCatalogo::class)->handle($actor, $atributo, ['nombre' => 'No persistir']), fn () => app(GuardarAtributoCategoria::class)->handle($actor, $categoria, $association, ['orden_presentacion' => 40])] as $operation) {
            try {
                $operation();
                $this->fail('Debe rechazarse una escritura con acceso revocado.');
            } catch (AuthorizationException) {
                $this->assertNotSame('No persistir', $atributo->fresh()->nombre);
                $this->assertSame(0, $association->fresh()->orden_presentacion);
            }
        }
        $this->signIn($actor->fresh())->put(route('atributos.update', $atributo), $this->attributeData())->assertForbidden();
        $this->put(route('atributos-categoria.update', [$categoria, $association]), $this->associationData())->assertForbidden();
    }

    public function test_actions_recheck_current_parent_state_and_parent_ownership(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $association = $this->association($categoria, $atributo, ['activo' => false]);
        $wrong = Categoria::factory()->create();
        try {
            app(GuardarAtributoCategoria::class)->handle($actor, $wrong, $association, ['activo' => true], statusOnly: true);
            $this->fail('La acción también debe comprobar la categoría.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        Categoria::whereKey($categoria->id)->update(['activo' => false]);
        $this->expectException(ValidationException::class);
        app(GuardarAtributoCategoria::class)->handle($actor, $categoria, $association, ['activo' => true], statusOnly: true);
    }

    public function test_both_forms_require_csrf(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $categoria = Categoria::factory()->create();
        $atributo = $this->technicalAttribute();
        $this->app->instance('env', 'local');
        $this->post(route('atributos.store'), $this->attributeData())->assertStatus(419);
        $this->post(route('atributos-categoria.store', $categoria), $this->associationData(['atributo_id' => $atributo->id]))->assertStatus(419);
    }

    public function test_new_permissions_are_configurable_through_existing_role_form_and_navigation(): void
    {
        $admin = Usuario::factory()->create(['rol_id' => 1]);
        $target = $this->customActor([]);
        $categoria = Categoria::factory()->create();
        $this->signIn($admin)->get(route('roles.edit', $target->rol))->assertOk()->assertSee('Atributos técnicos')->assertSee('Atributos por categoría');
        $this->put(route('roles.update', $target->rol), ['permisos' => Permiso::whereIn('codigo', ['panel.view', 'attributes.view', 'category_attributes.view'])->pluck('id')->all()])->assertRedirect();
        $this->signIn($target->fresh())->get(route('dashboard'))->assertOk()->assertSee('Consultar atributos técnicos');
        $this->get(route('atributos.index'))->assertOk();
        $this->get(route('atributos-categoria.index', $categoria))->assertOk();
        $this->get(route('atributos.create'))->assertForbidden();
    }
}
