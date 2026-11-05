<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductAttributeValueTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_all_four_types_zero_false_negative_decimal_and_dates_are_saved_exactly(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $values = [];
        foreach (['text' => '0', 'decimal' => '-2.125', 'boolean' => '0', 'date' => '2024-02-29'] as $tipo => $raw) {
            $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => $tipo]), ['obligatorio' => 1]);
            $values[$definition->id] = $raw;
        }
        $this->signIn($actor)->post(route('productos.store'), $this->productData(['categoria_id' => $categoria->id, 'values' => $values]))->assertSessionHasNoErrors();
        $producto = Producto::firstOrFail();
        $this->assertCount(4, $producto->valoresAtributos);
        $this->assertDatabaseHas('valores_atributo_producto', ['valor_decimal' => '-2.125000']);
        $this->assertDatabaseHas('valores_atributo_producto', ['valor_booleano' => 0]);
        $this->assertDatabaseCount('revisiones_valor_atributo', 4);
        $this->get(route('productos.show', $producto))->assertOk()->assertSee('Ver revisiones');
    }

    public function test_required_types_ranges_precision_and_category_membership_are_enforced(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        $categoria = Categoria::factory()->create();
        $unidad = UnidadMedida::factory()->create(['decimales' => 2]);
        $definition = $this->association($categoria, $this->technicalAttribute(['unidad_medida_id' => $unidad->id]), ['obligatorio' => 1, 'valor_minimo' => '-5', 'valor_maximo' => '5']);
        $data = $this->productData(['categoria_id' => $categoria->id]);
        foreach ([null, '', '5.01', '-5.01', '0.001', '1e2', '1,5', 1.5, ['x'], '1000000000000'] as $value) {
            $this->post(route('productos.store'), array_replace($data, ['values' => [$definition->id => $value]]))->assertSessionHasErrors('values.'.$definition->id);
        }
        $other = $this->association(Categoria::factory()->create(), $this->technicalAttribute());
        $this->post(route('productos.store'), array_replace($data, ['values' => [$other->id => '1']]))->assertSessionHasErrors('values');
        $this->post(route('productos.store'), array_replace($data, ['values' => [999999 => '1']]))->assertSessionHasErrors('values');
        $this->post(route('productos.store'), array_replace($data, ['values' => [$definition->id => '0.00']]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('valores_atributo_producto', ['valor_decimal' => '0.000000']);
    }

    public function test_invalid_boolean_text_and_calendar_dates_are_rejected(): void
    {
        $this->signIn(Usuario::factory()->create(['rol_id' => 1]));
        foreach (['boolean' => ['yes', 2, []], 'text' => [false, str_repeat('a', 256), []], 'date' => ['2025-02-29', '2024-04-31', '0000-00-00', '15/10/2026']] as $tipo => $invalid) {
            $categoria = Categoria::factory()->create();
            $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => $tipo]));
            $data = $this->productData(['categoria_id' => $categoria->id]);
            foreach ($invalid as $value) {
                $this->post(route('productos.store'), array_replace($data, ['values' => [$definition->id => $value]]))->assertSessionHasErrors('values.'.$definition->id);
            }
        }
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_inactive_definitions_cannot_be_written_and_required_values_are_checked_on_reactivation(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']), ['obligatorio' => 1]);
        $producto = $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'activo' => 0]);
        $this->signIn($actor)->patch(route('productos.estado', $producto), ['activo' => 1, 'version_bloqueo' => 1])->assertSessionHasErrors('values.'.$definition->id);
        $definition->update(['activo' => false]);
        $this->post(route('productos.store'), $this->productData(['categoria_id' => $categoria->id, 'values' => [$definition->id => 'No']]))->assertSessionHasErrors('values');
    }
}
