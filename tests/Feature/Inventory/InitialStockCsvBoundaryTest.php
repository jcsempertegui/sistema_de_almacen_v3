<?php

namespace Tests\Feature\Inventory;

use App\Models\Ubicacion;
use App\Models\UnidadIndividual;
use App\Services\CsvExistenciasIniciales;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InitialStockCsvBoundaryTest extends TestCase
{
    use CreatesInventory;

    private const HEADER = "product_code;location_code;cantidad;individual_identifier\n";

    private function parse(string $content): array
    {
        return app(CsvExistenciasIniciales::class)->parse(UploadedFile::fake()->createWithContent('limites.csv', $content));
    }

    private function rejected(string $content): void
    {
        try {
            $this->parse($content);
            $this->fail('El CSV inválido fue aceptado.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }
    }

    public function test_exact_byte_and_pair_limits(): void
    {
        $c = $this->inventoryContext();
        $ubicaciones = Ubicacion::factory()->count(501)->create();
        $rows = $ubicaciones->map(fn ($l) => $c['producto']->codigo_interno.';'.$l->codigo.";1;\n")->all();
        $content = self::HEADER.implode('', array_slice($rows, 0, 500));
        $padding = 1048576 - strlen($content);
        $padded = self::HEADER;
        foreach (array_slice($rows, 0, 500) as $i => $row) {
            $padded .= str_repeat(' ', intdiv($padding, 500) + ($i < $padding % 500 ? 1 : 0)).$row;
        }
        $this->assertSame(1048576, strlen($padded));
        $this->assertCount(500, $this->parse($padded)['lineas']);
        $this->rejected($padded.' ');
        $this->rejected(self::HEADER.implode('', $rows));
        $this->assertDatabaseCount('documentos_inventario', 0);
    }

    public function test_exact_row_limit_for_individuals_and_repetition_across_locations(): void
    {
        $c = $this->inventoryContext('individual');
        $unidades = UnidadIndividual::factory()->count(1001)->create(['producto_id' => $c['producto']->id, 'ubicacion_id' => null]);
        $rows = $unidades->map(fn ($u) => $c['producto']->codigo_interno.';'.$c['ubicacion']->codigo.';1;'.$u->identificador."\n")->all();
        $parsed = $this->parse(self::HEADER.implode('', array_slice($rows, 0, 1000)));
        $this->assertSame('1000', $parsed['lineas'][0]['cantidad']);
        $this->assertCount(1000, $parsed['lineas'][0]['unit_ids']);
        $this->rejected(self::HEADER.implode('', $rows));
        $other = Ubicacion::factory()->create();
        $this->rejected(self::HEADER.$rows[0].$c['producto']->codigo_interno.';'.$other->codigo.';1;'.$unidades[0]->identificador."\n");
    }

    public function test_encoding_structure_and_decimal_boundaries(): void
    {
        $c = $this->inventoryContext();
        $c['producto']->unidadMedida->update(['decimales' => 6]);
        $prefix = $c['producto']->codigo_interno.';'.$c['ubicacion']->codigo.';';
        $this->assertSame('0.000001', $this->parse("\xEF\xBB\xBF".self::HEADER.$prefix."0.000001;\r\n")['lineas'][0]['cantidad']);
        $this->assertSame('999999999999.999999', $this->parse(self::HEADER.$prefix."999999999999.999999;\n")['lineas'][0]['cantidad']);
        foreach (['0', '-1', '1e2', '1,2', '0.0000001', '1000000000000', '=1', '+1', '@x', "1\0", "\xFF"] as $value) {
            $this->rejected(self::HEADER.$prefix.$value.";\n");
        }
        foreach (['', self::HEADER, self::HEADER."\n", self::HEADER.$prefix."1;;\n", self::HEADER.$prefix."1\n"] as $content) {
            $this->rejected($content);
        }
        $c['producto']->unidadMedida->update(['decimales' => 0]);
        $this->rejected(self::HEADER.$prefix."1.1;\n");
        $this->assertSame('1', $this->parse(self::HEADER.$prefix."1.000000;\n")['lineas'][0]['cantidad']);
        $this->assertDatabaseCount('existencias_ubicacion', 0);
    }
}
