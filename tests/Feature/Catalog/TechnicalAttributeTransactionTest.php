<?php

namespace Tests\Feature\Catalog;

use App\Actions\Catalogo\GuardarAtributoCatalogo;
use App\Actions\Catalogo\GuardarAtributoCategoria;
use App\Models\Categoria;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesTechnicalCatalog;
use Tests\TestCase;

class TechnicalAttributeTransactionTest extends TestCase
{
    use CreatesTechnicalCatalog;

    public function test_duplicate_definitions_and_associations_are_translated_when_collision_reaches_the_action(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $atributo = $this->technicalAttribute(['codigo' => 'TEMP']);
        $categoria = Categoria::factory()->create();
        $this->association($categoria, $atributo);
        foreach ([
            ['codigo', fn () => app(GuardarAtributoCatalogo::class)->handle($actor, null, $this->attributeData())],
            ['atributo_id', fn () => app(GuardarAtributoCategoria::class)->handle($actor, $categoria, null, $this->associationData(['atributo_id' => $atributo->id]))],
        ] as [$field, $operation]) {
            try {
                $operation();
                $this->fail('La colisión SQL debe convertirse en validación.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
                $this->assertDatabaseCount('atributos', 1);
                $this->assertDatabaseCount('atributos_categoria', 1);
            }
        }
    }

    public function test_action_rechecks_associations_added_after_the_attribute_was_loaded(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $atributo = $this->technicalAttribute();
        $this->association(Categoria::factory()->create(), $atributo, ['activo' => false]);
        try {
            app(GuardarAtributoCatalogo::class)->handle($actor, $atributo, ['tipo_dato' => 'text', 'nombre' => 'No persistir']);
            $this->fail('La acción debe verificar las asociaciones actuales.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('tipo_dato', $exception->errors());
            $this->assertSame('decimal', $atributo->fresh()->tipo_dato);
            $this->assertNotSame('No persistir', $atributo->fresh()->nombre);
        }
    }

    public function test_action_rechecks_unit_state_after_validation_and_does_not_create_partial_data(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $unidad = UnidadMedida::factory()->create();
        UnidadMedida::whereKey($unidad->id)->update(['activo' => false]);
        try {
            app(GuardarAtributoCatalogo::class)->handle($actor, null, $this->attributeData(['unidad_medida_id' => $unidad->id]));
            $this->fail('La unidad debe comprobarse dentro de la transacción.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('unidad_medida_id', $exception->errors());
            $this->assertDatabaseCount('atributos', 0);
        }
    }

    public function test_action_rejects_raw_decimal_overprecision_before_eloquent_can_round(): void
    {
        $actor = Usuario::factory()->create(['rol_id' => 1]);
        $categoria = Categoria::factory()->create();
        $unidad = UnidadMedida::factory()->create(['decimales' => 2]);
        $atributo = $this->technicalAttribute(['unidad_medida_id' => $unidad->id]);
        foreach (['1.0000001', '1.001000'] as $minimum) {
            try {
                app(GuardarAtributoCategoria::class)->handle($actor, $categoria, null, $this->associationData(['atributo_id' => $atributo->id, 'valor_minimo' => $minimum]));
                $this->fail('No se debe redondear un límite inválido.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('valor_minimo', $exception->errors());
                $this->assertDatabaseCount('atributos_categoria', 0);
            }
        }
    }
}
