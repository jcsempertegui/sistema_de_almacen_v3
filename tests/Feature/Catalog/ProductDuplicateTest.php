<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductAttributeValidator;
use App\Services\ProductDuplicateFinder;
use Tests\Support\CreatesProductCatalog;
use Tests\TestCase;

class ProductDuplicateTest extends TestCase
{
    use CreatesProductCatalog;

    public function test_browser_form_round_trip_reviews_false_decimal_and_date_identity_without_merging(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $values = [];
        foreach (['boolean' => '0', 'decimal' => '1.0', 'date' => '2024-02-29'] as $type => $value) {
            $definition = $this->association($category, $this->technicalAttribute(['data_type' => $type]), ['is_identity' => true]);
            $values[$definition->id] = $value;
        }
        $first = $this->savedProduct($actor, ['category_id' => $category->id, 'values' => $values]);
        $data = $this->productData(['category_id' => $category->id, 'measurement_unit_id' => $first->measurement_unit_id, 'values' => $values, 'brand' => '<script>prueba</script>']);
        array_walk_recursive($data, function (&$value) {
            $value = $value === null ? '' : (string) $value;
        });
        $response = $this->signIn($actor)->post(route('products.preview'), $data)->assertOk()->assertSee($first->internal_code)->assertSee('&lt;script&gt;prueba&lt;/script&gt;', false);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $fields = [];
        foreach ((new \DOMXPath($document))->query('//form[contains(@action,"/productos")]/input[@type="hidden"]') as $input) {
            $fields[] = rawurlencode($input->getAttribute('name')).'='.rawurlencode($input->getAttribute('value'));
        }
        parse_str(implode('&', $fields), $submitted);
        $this->assertArrayHasKey('duplicate_token', $submitted);
        $this->post(route('products.store'), $submitted)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_attribute_value_revisions', 6);
    }

    public function test_equivalent_identity_warns_including_inactive_candidates_and_requires_review_of_current_payload(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(), ['is_identity' => 1]);
        $first = $this->savedProduct($actor, ['category_id' => $category->id, 'values' => [$definition->id => '1.0'], 'is_active' => 0]);
        $data = $this->productData(['category_id' => $category->id, 'measurement_unit_id' => $first->measurement_unit_id, 'values' => [$definition->id => '1.000000']]);
        $this->signIn($actor)->post(route('products.preview'), $data)->assertOk()->assertSee($first->internal_code)->assertSee('Inactivo')->assertSee('guardar producto distinto');
        $this->post(route('products.store'), $data)->assertSessionHasErrors('duplicate_token');
        $draft = new Product($data);
        $validator = app(ProductAttributeValidator::class);
        $finder = app(ProductDuplicateFinder::class);
        $definitions = $validator->definitions($category->id);
        $values = $validator->normalize($draft, $data['values'], $definitions);
        $candidates = $finder->candidates($draft, $values, $definitions);
        $this->assertSame([$first->id], $candidates->pluck('id')->all());
        $token = $finder->token($data, $values, $definitions, $candidates);
        $this->post(route('products.store'), array_replace($data, ['name' => 'Cambio posterior', 'duplicate_token' => $token]))->assertSessionHasErrors('duplicate_token');
        $this->post(route('products.store'), $data + ['duplicate_token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 2);
    }

    public function test_text_identity_is_case_sensitive_and_empty_identity_never_claims_equivalence(): void
    {
        $actor = User::factory()->create(['role_id' => 1]);
        $category = Category::factory()->create();
        $definition = $this->association($category, $this->technicalAttribute(['data_type' => 'text']), ['is_identity' => 1]);
        $first = $this->savedProduct($actor, ['category_id' => $category->id, 'values' => [$definition->id => 'AbC']]);
        $data = $this->productData(['category_id' => $category->id, 'measurement_unit_id' => $first->measurement_unit_id, 'values' => [$definition->id => 'abc']]);
        $this->signIn($actor)->post(route('products.store'), $data)->assertSessionHasNoErrors();
        $data['internal_code'] = 'NO-IDENTITY';
        $data['values'] = [];
        $this->post(route('products.preview'), $data)->assertOk()->assertSee('comparación es insuficiente');
        $this->post(route('products.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('products', 3);
    }
}
