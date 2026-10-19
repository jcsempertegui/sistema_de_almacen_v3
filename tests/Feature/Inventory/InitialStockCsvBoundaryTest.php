<?php

namespace Tests\Feature\Inventory;

use App\Models\IndividualUnit;
use App\Models\Location;
use App\Services\InitialStockCsv;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InitialStockCsvBoundaryTest extends TestCase
{
    use CreatesInventory;

    private const HEADER = "product_code;location_code;quantity;individual_identifier\n";

    private function parse(string $content): array
    {
        return app(InitialStockCsv::class)->parse(UploadedFile::fake()->createWithContent('limites.csv', $content));
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
        $locations = Location::factory()->count(501)->create();
        $rows = $locations->map(fn ($l) => $c['product']->internal_code.';'.$l->code.";1;\n")->all();
        $content = self::HEADER.implode('', array_slice($rows, 0, 500));
        $padding = 1048576 - strlen($content);
        $padded = self::HEADER;
        foreach (array_slice($rows, 0, 500) as $i => $row) {
            $padded .= str_repeat(' ', intdiv($padding, 500) + ($i < $padding % 500 ? 1 : 0)).$row;
        }
        $this->assertSame(1048576, strlen($padded));
        $this->assertCount(500, $this->parse($padded)['lines']);
        $this->rejected($padded.' ');
        $this->rejected(self::HEADER.implode('', $rows));
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_exact_row_limit_for_individuals_and_repetition_across_locations(): void
    {
        $c = $this->inventoryContext('individual');
        $units = IndividualUnit::factory()->count(1001)->create(['product_id' => $c['product']->id, 'location_id' => null]);
        $rows = $units->map(fn ($u) => $c['product']->internal_code.';'.$c['location']->code.';1;'.$u->identifier."\n")->all();
        $parsed = $this->parse(self::HEADER.implode('', array_slice($rows, 0, 1000)));
        $this->assertSame('1000', $parsed['lines'][0]['quantity']);
        $this->assertCount(1000, $parsed['lines'][0]['unit_ids']);
        $this->rejected(self::HEADER.implode('', $rows));
        $other = Location::factory()->create();
        $this->rejected(self::HEADER.$rows[0].$c['product']->internal_code.';'.$other->code.';1;'.$units[0]->identifier."\n");
    }

    public function test_encoding_structure_and_decimal_boundaries(): void
    {
        $c = $this->inventoryContext();
        $c['product']->measurementUnit->update(['decimal_places' => 6]);
        $prefix = $c['product']->internal_code.';'.$c['location']->code.';';
        $this->assertSame('0.000001', $this->parse("\xEF\xBB\xBF".self::HEADER.$prefix."0.000001;\r\n")['lines'][0]['quantity']);
        $this->assertSame('999999999999.999999', $this->parse(self::HEADER.$prefix."999999999999.999999;\n")['lines'][0]['quantity']);
        foreach (['0', '-1', '1e2', '1,2', '0.0000001', '1000000000000', '=1', '+1', '@x', "1\0", "\xFF"] as $value) {
            $this->rejected(self::HEADER.$prefix.$value.";\n");
        }
        foreach (['', self::HEADER, self::HEADER."\n", self::HEADER.$prefix."1;;\n", self::HEADER.$prefix."1\n"] as $content) {
            $this->rejected($content);
        }
        $c['product']->measurementUnit->update(['decimal_places' => 0]);
        $this->rejected(self::HEADER.$prefix."1.1;\n");
        $this->assertSame('1', $this->parse(self::HEADER.$prefix."1.000000;\n")['lines'][0]['quantity']);
        $this->assertDatabaseCount('stock_positions', 0);
    }
}
