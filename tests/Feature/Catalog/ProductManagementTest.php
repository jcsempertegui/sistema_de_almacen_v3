<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\MeasurementUnit;
use App\Models\Product;
use App\Models\User;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_validation_after_review_returns_to_a_get_form_with_input_and_errors(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $data = $this->productData(['is_loanable' => 1]);
        $this->signIn($actor)->from(route('products.preview'))->post(route('products.store'), $data)
            ->assertRedirect(route('products.create', ['category_id' => $data['category_id']]))->assertSessionHasErrors('control_mode')->assertSessionHasInput('name');
        $invalid = $data;
        $invalid['name'] = '';
        $this->from(route('products.preview'))->post(route('products.preview'), $invalid)->assertRedirect(route('products.create', ['category_id' => $data['category_id']]))->assertSessionHasErrors('name');
    }

    public function test_managers_register_edit_search_toggle_and_render_all_product_screens(): void
    {
        foreach ([1, 2] as $role) {
            $actor = User::factory()->create(['role_id' => $role]);
            $data = $this->productData(['internal_code' => ' pr-'.$role.' ']);
            $this->signIn($actor)->get(route('products.create', ['category_id' => $data['category_id']]))->assertOk()->assertSee('Nuevo producto');
            $this->post(route('products.preview'), $data)->assertOk()->assertSee('Revisar producto');
            $this->post(route('products.store'), $data + ['created_by' => 999, 'updated_by' => 999])->assertSessionHasNoErrors();
            $product = Product::where('internal_code', 'PR-'.$role)->firstOrFail();
            $this->assertSame($actor->id, $product->created_by);
            $this->get(route('products.edit', $product))->assertOk();
            $this->get(route('products.show', $product))->assertOk();
            $this->put(route('products.update', $product), $this->productPayload($product, ['name' => 'Nombre corregido']))->assertSessionHasNoErrors();
            $this->assertSame(2, $product->fresh()->lock_version);
            $this->get(route('products.index', ['q' => 'corregido', 'category_id' => $product->category_id, 'measurement_unit_id' => $product->measurement_unit_id, 'control_mode' => 'quantity', 'status' => 'active']))->assertOk()->assertSee('Nombre corregido');
            foreach ([0, 1] as $state) {
                $this->patch(route('products.status', $product), ['is_active' => $state, 'lock_version' => $product->fresh()->lock_version])->assertSessionHasNoErrors();
                $this->assertSame((bool) $state, $product->fresh()->is_active);
            }
            $this->delete(route('products.show', $product))->assertStatus(405);
        }
    }

    public function test_required_fields_lengths_flags_references_and_loan_mode_are_validated(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        $data = $this->productData();
        foreach (['internal_code', 'name', 'category_id', 'measurement_unit_id', 'control_mode', 'is_active', 'is_loanable'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post(route('products.store'), $invalid)->assertSessionHasErrors($field);
        }
        foreach ([['internal_code' => str_repeat('A', 61)], ['name' => ' '], ['brand' => str_repeat('A', 101)], ['manufacturer_reference' => str_repeat('A', 101)], ['control_mode' => 'Individual'], ['is_loanable' => 2], ['is_active' => 2], ['category_id' => 999999], ['measurement_unit_id' => 999999], ['is_loanable' => 1, 'control_mode' => 'quantity']] as $changes) {
            $this->post(route('products.store'), array_replace($data, $changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('products', 0);
        $this->post(route('products.store'), array_replace($data, ['is_loanable' => 1, 'control_mode' => 'individual']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['is_loanable' => 1, 'control_mode' => 'individual']);
    }

    public function test_codes_remain_reserved_and_immutable_and_stale_edits_are_rejected(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $product = $this->savedProduct($actor, ['is_active' => 0]);
        $this->signIn($actor)->post(route('products.store'), $this->productData(['internal_code' => strtolower($product->internal_code)]))->assertSessionHasErrors('internal_code');
        $this->put(route('products.update', $product), $this->productPayload($product, ['internal_code' => 'NEW']))->assertSessionHasErrors('internal_code');
        $this->put(route('products.update', $product), $this->productPayload($product, ['name' => 'Primero']))->assertSessionHasNoErrors();
        $this->put(route('products.update', $product), $this->productPayload($product, ['name' => 'Obsoleto']))->assertStatus(409);
        $this->assertSame('Primero', $product->fresh()->name);
    }

    public function test_inactive_catalog_references_are_rejected_without_creating_products(): void
    {
        $this->signIn(User::factory()->create(['role_id' => 1]));
        foreach (['category_id' => Category::class, 'measurement_unit_id' => MeasurementUnit::class] as $field => $model) {
            $reference = $model::factory()->create(['is_active' => false]);
            $this->post(route('products.store'), $this->productData([$field => $reference->id]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('products', 0);
    }
}
