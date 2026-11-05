<?php

namespace Tests\Feature\Catalog;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\BuscadorDuplicadosProducto;
use App\Services\ValidadorAtributosProducto;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductDuplicateTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_browser_form_round_trip_reviews_false_decimal_and_date_identity_without_merging(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $values = [];
        foreach (['boolean' => '0', 'decimal' => '1.0', 'date' => '2024-02-29'] as $tipo => $value) {
            $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => $tipo]), ['identificacion_tecnica' => true]);
            $values[$definition->id] = $value;
        }
        $first = $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => $values]);
        $data = $this->productData(['categoria_id' => $categoria->id, 'unidad_medida_id' => $first->unidad_medida_id, 'values' => $values, 'marca' => '<script>prueba</script>']);
        array_walk_recursive($data, function (&$value) {
            $value = $value === null ? '' : (string) $value;
        });
        $response = $this->signIn($actor)->post(route('productos.preview'), $data)->assertOk()->assertSee($first->codigo_interno)->assertSee('&lt;script&gt;prueba&lt;/script&gt;', false);
        $documento = new \DOMDocument;
        @$documento->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $fields = [];
        foreach ((new \DOMXPath($documento))->query('//form[contains(@action,"/productos")]/input[@type="hidden"]') as $input) {
            $fields[] = rawurlencode($input->getAttribute('name')).'='.rawurlencode($input->getAttribute('value'));
        }
        parse_str(implode('&', $fields), $submitted);
        $this->assertArrayHasKey('duplicate_token', $submitted);
        $this->post(route('productos.store'), $submitted)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('productos', 2);
        $this->assertDatabaseCount('revisiones_valor_atributo', 6);
    }

    public function test_equivalent_identity_warns_including_inactive_candidates_and_requires_review_of_current_payload(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(), ['identificacion_tecnica' => 1]);
        $first = $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => [$definition->id => '1.0'], 'activo' => 0]);
        $data = $this->productData(['categoria_id' => $categoria->id, 'unidad_medida_id' => $first->unidad_medida_id, 'values' => [$definition->id => '1.000000']]);
        $this->signIn($actor)->post(route('productos.preview'), $data)->assertOk()->assertSee($first->codigo_interno)->assertSee('Inactivo')->assertSee('guardar producto distinto');
        $this->post(route('productos.store'), $data)->assertSessionHasErrors('duplicate_token');
        $draft = new Producto($data);
        $validator = app(ValidadorAtributosProducto::class);
        $finder = app(BuscadorDuplicadosProducto::class);
        $definitions = $validator->definitions($categoria->id);
        $values = $validator->normalize($draft, $data['values'], $definitions);
        $candidates = $finder->candidates($draft, $values, $definitions);
        $this->assertSame([$first->id], $candidates->pluck('id')->all());
        $token = $finder->token($data, $values, $definitions, $candidates);
        $this->post(route('productos.store'), array_replace($data, ['nombre' => 'Cambio posterior', 'duplicate_token' => $token]))->assertSessionHasErrors('duplicate_token');
        $this->post(route('productos.store'), $data + ['duplicate_token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('productos', 2);
    }

    public function test_text_identity_is_case_sensitive_and_empty_identity_never_claims_equivalence(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $definition = $this->association($categoria, $this->technicalAttribute(['tipo_dato' => 'text']), ['identificacion_tecnica' => 1]);
        $first = $this->savedProduct($actor, ['categoria_id' => $categoria->id, 'values' => [$definition->id => 'AbC']]);
        $data = $this->productData(['categoria_id' => $categoria->id, 'unidad_medida_id' => $first->unidad_medida_id, 'values' => [$definition->id => 'abc']]);
        $this->signIn($actor)->post(route('productos.store'), $data)->assertSessionHasNoErrors();
        $data['codigo_interno'] = 'NO-IDENTITY';
        $data['values'] = [];
        $this->post(route('productos.preview'), $data)->assertOk()->assertSee('comparación es insuficiente');
        $this->post(route('productos.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('productos', 3);
    }
}
