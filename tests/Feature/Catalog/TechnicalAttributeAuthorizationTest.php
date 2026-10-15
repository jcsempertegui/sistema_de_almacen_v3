<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalog\SaveCatalogAttribute;
use App\Actions\Catalog\SaveCategoryAttribute;
use App\Actions\Roles\SaveRole;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $association = $this->association($category, $attribute, ['is_active' => false]);

        return [
            ['attributes', 'attributes', $attribute, $attribute, [], $this->attributeData(['code' => 'NEW']), $this->attributeData(['code' => $attribute->code])],
            ['category_attributes', 'category-attributes', [$category, $association], $association, [$category], $this->associationData(['attribute_id' => $this->technicalAttribute()->id]), $this->associationData(['is_active' => 0])],
        ];
    }

    private function customActor(array $codes): User
    {
        $role = Role::create(['code' => 'technical_'.bin2hex(random_bytes(4)), 'name' => 'Administrador']);
        $role->permissions()->attach(Permission::whereIn('code', $codes)->pluck('id')->all(), ['is_active' => true]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_auditor_can_read_but_cannot_mutate_either_entity_through_direct_requests(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 3]));
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $parent, $createData, $editData]) {
            $this->get(route($route.'.index', $parent))->assertOk()->assertDontSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.create', $parent))->assertForbidden();
            $this->get(route($route.'.edit', $parameters))->assertForbidden();
            $this->post(route($route.'.store', $parent), $createData)->assertForbidden();
            $this->put(route($route.'.update', $parameters), $editData)->assertForbidden();
            $this->patch(route($route.'.status', $parameters), ['is_active' => 1])->assertForbidden();
        }
    }

    public function test_guests_and_inactive_accounts_cannot_read_or_write(): void
    {
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $parent, $createData]) {
            $this->get(route($route.'.index', $parent))->assertRedirect(route('login'));
            $this->post(route($route.'.store', $parent), $createData)->assertRedirect(route('login'));
        }
        $inactive = User::factory()->create(['role_id' => 1, 'is_active' => false]);
        $category = Category::factory()->create();
        foreach ([['attributes.index', []], ['category-attributes.index', $category]] as [$route, $parameters]) {
            $this->signIn($inactive)->getJson(route($route, $parameters))->assertUnauthorized();
        }
    }

    public function test_custom_permissions_are_independent_and_role_name_does_not_grant_privileges(): void
    {
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $parent, $createData, $editData]) {
            $this->signIn($this->customActor([$prefix.'.view', $prefix.'.update']));
            $this->get(route($route.'.index', $parent))->assertOk()->assertSee('Editar')->assertDontSee('Desactivar');
            $this->get(route($route.'.edit', $parameters))->assertOk();
            $this->put(route($route.'.update', $parameters), $editData)->assertRedirect()->assertSessionHasNoErrors();
            $this->post(route($route.'.store', $parent), $createData)->assertForbidden();
            $this->patch(route($route.'.status', $parameters), ['is_active' => 1])->assertForbidden();
            $this->put(route($route.'.update', $parameters), array_replace($editData, ['is_active' => ! $target->is_active]))->assertForbidden();
            $this->signIn($this->customActor([]))->get(route($route.'.index', $parent))->assertForbidden();
        }
    }

    public function test_create_and_status_permissions_do_not_grant_metadata_editing(): void
    {
        foreach ($this->resources() as [$prefix, $route, $parameters, $target, $parent, $createData, $editData]) {
            $this->signIn($this->customActor([$prefix.'.create', $prefix.'.change_status']));
            $this->get(route($route.'.create', $parent))->assertOk();
            $this->post(route($route.'.store', $parent), $createData)->assertRedirect()->assertSessionHasNoErrors();
            $this->put(route($route.'.update', $parameters), $editData)->assertForbidden();
            $this->patch(route($route.'.status', $parameters), ['is_active' => 1, 'name' => 'Manipulado', 'display_order' => 600])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertNotSame('Manipulado', $target->fresh()->name);
            $this->assertNotSame(600, $target->fresh()->display_order);
        }
    }

    public function test_revocation_invalidates_sessions_and_rejects_in_flight_mutations_for_both_entities(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $actor = User::factory()->create(['role_id' => 2]);
        $actor->load('role');
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $association = $this->association($category, $attribute, ['is_active' => false]);
        $this->signIn($actor);
        $codes = array_diff($actor->role->assignedPermissionCodes(), ['attributes.update', 'category_attributes.update']);
        app(SaveRole::class)->handle($admin, $actor->role, ['permissions' => Permission::whereIn('code', $codes)->pluck('id')->all()]);
        $this->putJson(route('attributes.update', $attribute), $this->attributeData())->assertUnauthorized();
        foreach ([fn () => app(SaveCatalogAttribute::class)->handle($actor, $attribute, ['name' => 'No persistir']), fn () => app(SaveCategoryAttribute::class)->handle($actor, $category, $association, ['display_order' => 40])] as $operation) {
            try {
                $operation();
                $this->fail('Debe rechazarse una escritura con acceso revocado.');
            } catch (AuthorizationException) {
                $this->assertNotSame('No persistir', $attribute->fresh()->name);
                $this->assertSame(0, $association->fresh()->display_order);
            }
        }
        $this->signIn($actor->fresh())->put(route('attributes.update', $attribute), $this->attributeData())->assertForbidden();
        $this->put(route('category-attributes.update', [$category, $association]), $this->associationData())->assertForbidden();
    }

    public function test_actions_recheck_current_parent_state_and_parent_ownership(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $association = $this->association($category, $attribute, ['is_active' => false]);
        $wrong = Category::factory()->create();
        try {
            app(SaveCategoryAttribute::class)->handle($actor, $wrong, $association, ['is_active' => true], statusOnly: true);
            $this->fail('La acción también debe comprobar la categoría.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        Category::whereKey($category->id)->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        app(SaveCategoryAttribute::class)->handle($actor, $category, $association, ['is_active' => true], statusOnly: true);
    }

    public function test_both_forms_require_csrf(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $category = Category::factory()->create();
        $attribute = $this->technicalAttribute();
        $this->app->instance('env', 'local');
        $this->post(route('attributes.store'), $this->attributeData())->assertStatus(419);
        $this->post(route('category-attributes.store', $category), $this->associationData(['attribute_id' => $attribute->id]))->assertStatus(419);
    }

    public function test_new_permissions_are_configurable_through_existing_role_form_and_navigation(): void
    {
        $admin = User::factory()->create(['role_id' => 1]);
        $target = $this->customActor([]);
        $category = Category::factory()->create();
        $this->signIn($admin)->get(route('roles.edit', $target->role))->assertOk()->assertSee('Atributos técnicos')->assertSee('Atributos por categoría');
        $this->put(route('roles.update', $target->role), ['permissions' => Permission::whereIn('code', ['panel.view', 'attributes.view', 'category_attributes.view'])->pluck('id')->all()])->assertRedirect();
        $this->signIn($target->fresh())->get(route('dashboard'))->assertOk()->assertSee('Consultar atributos técnicos');
        $this->get(route('attributes.index'))->assertOk();
        $this->get(route('category-attributes.index', $category))->assertOk();
        $this->get(route('attributes.create'))->assertForbidden();
    }
}
