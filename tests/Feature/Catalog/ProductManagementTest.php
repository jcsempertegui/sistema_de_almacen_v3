<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_validation_after_review_returns_to_a_get_form_with_input_and_errors(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $data = $this->productData(['prestable' => 1]);
        $this->signIn($actor)->from(route('productos.preview'))->post(route('productos.store'), $data)
            ->assertRedirect(route('productos.create', ['categoria_id' => $data['categoria_id']]))->assertSessionHasErrors('modo_control')->assertSessionHasInput('nombre');
        $invalid = $data;
        $invalid['nombre'] = '';
        $this->from(route('productos.preview'))->post(route('productos.preview'), $invalid)->assertRedirect(route('productos.create', ['categoria_id' => $data['categoria_id']]))->assertSessionHasErrors('nombre');
    }

    public function test_managers_register_edit_search_toggle_and_render_all_product_screens(): void
    {
        foreach ([1, 2] as $rol) {
            $actor = Usuario::factory()->create(['rol_id' => $rol]);
            $data = $this->productData(['codigo_interno' => ' pr-'.$rol.' ']);
            $this->signIn($actor)->get(route('productos.create', ['categoria_id' => $data['categoria_id']]))->assertOk()->assertSee('Nuevo producto');
            $this->post(route('productos.preview'), $data)->assertOk()->assertSee('Revisar producto');
            $this->post(route('productos.store'), $data + ['creado_por' => 999, 'modificado_por' => 999])->assertSessionHasNoErrors();
            $producto = Producto::where('codigo_interno', 'PR-'.$rol)->firstOrFail();
            $this->assertSame($actor->id, $producto->creado_por);
            $this->get(route('productos.edit', $producto))->assertOk();
            $this->get(route('productos.show', $producto))->assertOk();
            $this->put(route('productos.update', $producto), $this->productPayload($producto, ['nombre' => 'Nombre corregido']))->assertSessionHasNoErrors();
            $this->assertSame(2, $producto->fresh()->version_bloqueo);
            $this->get(route('productos.index', ['q' => 'corregido', 'categoria_id' => $producto->categoria_id, 'unidad_medida_id' => $producto->unidad_medida_id, 'modo_control' => 'quantity', 'estado' => 'active']))->assertOk()->assertSee('Nombre corregido');
            foreach ([0, 1] as $state) {
                $this->patch(route('productos.estado', $producto), ['activo' => $state, 'version_bloqueo' => $producto->fresh()->version_bloqueo])->assertSessionHasNoErrors();
                $this->assertSame((bool) $state, $producto->fresh()->activo);
            }
            $this->delete(route('productos.show', $producto))->assertStatus(405);
        }
    }

    public function test_required_fields_lengths_flags_references_and_loan_mode_are_validated(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $data = $this->productData();
        foreach (['codigo_interno', 'nombre', 'categoria_id', 'unidad_medida_id', 'modo_control', 'activo', 'prestable'] as $field) {
            $invalid = $data;
            unset($invalid[$field]);
            $this->post(route('productos.store'), $invalid)->assertSessionHasErrors($field);
        }
        foreach ([['codigo_interno' => str_repeat('A', 61)], ['nombre' => ' '], ['marca' => str_repeat('A', 101)], ['referencia_fabricante' => str_repeat('A', 101)], ['modo_control' => 'Individual'], ['prestable' => 2], ['activo' => 2], ['categoria_id' => 999999], ['unidad_medida_id' => 999999], ['prestable' => 1, 'modo_control' => 'quantity']] as $changes) {
            $this->post(route('productos.store'), array_replace($data, $changes))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('productos', 0);
        $this->post(route('productos.store'), array_replace($data, ['prestable' => 1, 'modo_control' => 'individual']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productos', ['prestable' => 1, 'modo_control' => 'individual']);
    }

    public function test_codes_remain_reserved_and_immutable_and_stale_edits_are_rejected(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $producto = $this->savedProduct($actor, ['activo' => 0]);
        $this->signIn($actor)->post(route('productos.store'), $this->productData(['codigo_interno' => strtolower($producto->codigo_interno)]))->assertSessionHasErrors('codigo_interno');
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['codigo_interno' => 'NEW']))->assertSessionHasErrors('codigo_interno');
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['nombre' => 'Primero']))->assertSessionHasNoErrors();
        $this->put(route('productos.update', $producto), $this->productPayload($producto, ['nombre' => 'Obsoleto']))->assertStatus(409);
        $this->assertSame('Primero', $producto->fresh()->nombre);
    }

    public function test_inactive_catalog_references_are_rejected_without_creating_products(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['categoria_id' => Categoria::class, 'unidad_medida_id' => UnidadMedida::class] as $field => $model) {
            $reference = $model::factory()->create(['activo' => false]);
            $this->post(route('productos.store'), $this->productData([$field => $reference->id]))->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('productos', 0);
    }
}
