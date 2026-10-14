<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\User;
use App\Services\CatalogReferences;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    private function payload(array $changes = []): array
    {
        return array_replace(['code' => 'HERR', 'name' => 'Herramientas', 'description' => 'Herramientas manuales', 'is_active' => 1], $changes);
    }

    public function test_administrator_and_manager_can_register_edit_and_change_status(): void
    {
        foreach ([1, 2] as $roleId) {
            $actor = User::factory()->create(['role_id' => $roleId]);
            $this->signIn($actor)->get(route('categories.create'))->assertOk()->assertSee('Nueva categoría');
            $this->post(route('categories.store'), $this->payload(['code' => ' cat-'.$roleId.' ', 'name' => 'Categoría '.$roleId, 'created_by' => 999, 'updated_by' => 999]))->assertRedirect(route('categories.index'));
            $category = Category::where('code', 'CAT-'.$roleId)->firstOrFail();
            $this->assertSame($actor->id, $category->created_by);
            $this->assertSame($actor->id, $category->updated_by);
            $this->assertSame($actor->id, $category->creator->id);
            $this->get(route('categories.edit', $category))->assertOk();
            $this->put(route('categories.update', $category), $this->payload(['code' => $category->code, 'name' => 'Categoría editada '.$roleId, 'description' => 'Descripción actualizada']))->assertRedirect();
            $this->patch(route('categories.status', $category), ['is_active' => 0, 'name' => 'Manipulado'])->assertRedirect();
            $this->assertFalse($category->fresh()->is_active);
            $this->assertSame('Categoría editada '.$roleId, $category->fresh()->name);
            $this->patch(route('categories.status', $category), ['is_active' => 1])->assertRedirect();
            $this->assertTrue($category->fresh()->is_active);
        }
    }

    public function test_category_fields_are_required_and_lengths_and_state_are_validated_in_spanish(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (['code', 'name', 'is_active'] as $field) {
            $payload = $this->payload();
            unset($payload[$field]);
            $this->post(route('categories.store'), $payload)->assertSessionHasErrors($field);
        }
        foreach ([['code' => '   '], ['name' => '   '], ['code' => str_repeat('x', 51)], ['name' => str_repeat('x', 151)], ['description' => str_repeat('x', 10001)], ['is_active' => 2]] as $changes) {
            $this->post(route('categories.store'), $this->payload($changes))->assertSessionHasErrors();
        }
        $this->post(route('categories.store'), $this->payload(['name' => '']))->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_duplicate_codes_and_names_are_rejected_even_when_inactive(): void
    {
        $existing = Category::factory()->create(['code' => 'HERR', 'name' => 'Herramientas', 'is_active' => false]);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->post(route('categories.store'), $this->payload(['code' => ' herr ', 'name' => 'Otra']))->assertSessionHasErrors('code');
        $this->post(route('categories.store'), $this->payload(['code' => 'OTRA', 'name' => 'herramientas']))->assertSessionHasErrors('name');
        $this->assertDatabaseCount('categories', 1);
        $this->assertFalse($existing->fresh()->is_active);
    }

    public function test_editing_ignores_own_unique_fields_and_preserves_creator(): void
    {
        $creator = User::factory()->create(['role_id' => 1]);
        $editor = User::factory()->create(['role_id' => 2]);
        $category = Category::factory()->create(['created_by' => $creator->id, 'updated_by' => $creator->id]);
        $other = Category::factory()->create();
        $this->signIn($editor)->put(route('categories.update', $category), $this->payload(['code' => $category->code, 'name' => $category->name, 'created_by' => $editor->id]))->assertRedirect();
        $this->assertSame($creator->id, $category->fresh()->created_by);
        $this->assertSame($editor->id, $category->fresh()->updater->id);
        $this->put(route('categories.update', $category), $this->payload(['code' => $other->code]))->assertSessionHasErrors('code');
        $this->put(route('categories.update', $category), $this->payload(['code' => $category->code, 'name' => $other->name]))->assertSessionHasErrors('name');
    }

    public function test_search_filter_and_pagination_keep_filters_and_treat_wildcards_literally(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 3]));
        Category::factory()->count(22)->sequence(fn ($sequence) => ['name' => 'Filtro categoría '.$sequence->index, 'is_active' => false])->create();
        $this->get(route('categories.index', ['q' => 'Filtro', 'status' => 'inactive']))->assertOk()->assertSee('Página 1 de 2')->assertSee('status=inactive', false);
        $this->get(route('categories.index', ['q' => 'Filtro', 'status' => 'active']))->assertOk()->assertSee('No se encontraron categorías.');
        $category = Category::factory()->create(['code' => 'CAT%_ESPECIAL']);
        $this->get(route('categories.index', ['q' => '%_']))->assertOk()->assertSee($category->name)->assertDontSee('Filtro categoría');
        $this->get(route('categories.index', ['q' => str_repeat('x', 101)]))->assertSessionHasErrors('q');
        $this->get(route('categories.index', ['status' => 'wrong']))->assertSessionHasErrors('status');
    }

    public function test_deactivation_dependency_guard_blocks_both_endpoints_and_keeps_data(): void
    {
        $category = Category::factory()->create();
        $this->partialMock(CatalogReferences::class)->shouldReceive('categoryHasActiveProducts')->twice()->with($category->id)->andReturn(true);
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $this->patch(route('categories.status', $category), ['is_active' => 0])->assertSessionHasErrors('is_active');
        $this->put(route('categories.update', $category), $this->payload(['code' => $category->code, 'name' => 'Cambio rechazado', 'is_active' => 0]))->assertSessionHasErrors('is_active');
        $this->assertTrue($category->fresh()->is_active);
        $this->assertNotSame('Cambio rechazado', $category->fresh()->name);
    }

    public function test_without_future_tables_deactivation_is_allowed_and_records_are_retained(): void
    {
        $category = Category::factory()->create();
        $this->assertFalse(app(CatalogReferences::class)->categoryHasActiveProducts($category->id));
        $this->signIn(User::factory()->create(['role_id' => 1]))->patch(route('categories.status', $category), ['is_active' => 0])->assertRedirect();
        $this->delete('/categorias/'.$category->id)->assertStatus(405);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'code' => $category->code]);
    }

    public function test_category_html_is_escaped(): void
    {
        Category::factory()->create(['name' => '<script>alert(1)</script>', 'description' => '<img src=x onerror=alert(1)>']);
        $this->signIn(User::factory()->create(['role_id' => 3]))->get(route('categories.index'))->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<img src=x', false);
    }
}
