<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InitialStockImportTest extends TestCase
{
    use CreatesInventory;

    private function csv(array $c, string $quantity = '4', string $identifier = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('apertura.csv', "product_code;location_code;quantity;individual_identifier\r\n".$c['product']->internal_code.';'.$c['location']->code.';'.$quantity.';'.$identifier."\r\n");
    }

    public function test_csv_preview_is_read_only_and_save_only_creates_a_draft(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor'])->get(route('initial-stock-import.create'))->assertOk();
        $response = $this->post(route('initial-stock-import.preview'), ['file' => $this->csv($c), 'document_date' => '2026-10-19', 'cutoff_date' => '2026-10-18'])->assertOk()->assertSee('Guardar como borrador');
        $this->assertDatabaseCount('inventory_documents', 0);
        $token = $response->viewData('token');
        $this->post(route('initial-stock-import.store'), ['token' => $token])->assertSessionHasNoErrors();
        $document = InventoryDocument::firstOrFail();
        $this->assertSame('csv', $document->source);
        $this->assertSame('draft', $document->status);
        $this->assertSame(64, strlen($document->source_sha256));
        $this->assertDatabaseCount('stock_positions', 0);
        $this->post(route('inventory-documents.confirm', $document), ['lock_version' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('stock_positions', ['quantity' => '4.000000']);
        $this->post(route('initial-stock-import.store'), ['token' => $token])->assertSessionHasNoErrors();
        $repeated = InventoryDocument::latest('id')->firstOrFail();
        $this->get(route('inventory-documents.show', $repeated))->assertOk()->assertSee('El mismo archivo CSV');
        $this->post(route('inventory-documents.confirm', $repeated), ['lock_version' => 1])->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_import_rejects_unknown_catalog_formulas_duplicates_and_changed_catalog(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        $header = "product_code;location_code;quantity;individual_identifier\n";
        $line = $c['product']->internal_code.';'.$c['location']->code.";1;\n";
        foreach ([$header."UNKNOWN;UNKNOWN;1;\n", $header.$line.$line, $header.$c['product']->internal_code.';'.$c['location']->code.";=1+1;\n", "invalid;header\n"] as $content) {
            $this->post(route('initial-stock-import.preview'), ['file' => UploadedFile::fake()->createWithContent('x.csv', $content), 'document_date' => '2026-10-19', 'cutoff_date' => '2026-10-18'])->assertSessionHasErrors('file');
        }
        $response = $this->post(route('initial-stock-import.preview'), ['file' => $this->csv($c), 'document_date' => '2026-10-19', 'cutoff_date' => '2026-10-18'])->assertOk();
        $c['product']->update(['is_active' => false]);
        $this->post(route('initial-stock-import.store'), ['token' => $response->viewData('token')])->assertSessionHasErrors();
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_import_token_rejects_tampering_expiration_and_another_operator(): void
    {
        $c = $this->inventoryContext();
        $response = $this->signIn($c['actor'])->post(route('initial-stock-import.preview'), ['file' => $this->csv($c), 'document_date' => '2026-10-19', 'cutoff_date' => '2026-10-18'])->assertOk();
        $token = $response->viewData('token');
        $this->post(route('initial-stock-import.store'), ['token' => $token.'tampered'])->assertSessionHasErrors('file');
        $payload = json_decode(Crypt::decryptString($token), true);
        $payload['expires'] = now()->subMinute()->timestamp;
        $this->post(route('initial-stock-import.store'), ['token' => Crypt::encryptString(json_encode($payload))])->assertStatus(419);
        $payload['expires'] = now()->addMinute()->timestamp;
        $payload['actor']++;
        $this->post(route('initial-stock-import.store'), ['token' => Crypt::encryptString(json_encode($payload))])->assertStatus(419);
        $this->assertDatabaseCount('inventory_documents', 0);
    }

    public function test_individual_import_aggregates_existing_identifiers_and_rejects_repeated_identity(): void
    {
        $c = $this->inventoryContext('individual');
        $this->signIn($c['actor']);
        $response = $this->post(route('initial-stock-import.preview'), ['file' => $this->csv($c, '1', $c['unit']->identifier), 'document_date' => '2026-10-19', 'cutoff_date' => '2026-10-18'])->assertOk();
        $this->assertSame([$c['unit']->id], $response->viewData('lines')[0]['unit_ids']);
        $this->post(route('initial-stock-import.store'), ['token' => $response->viewData('token')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_document_line_units', 1);
        $this->assertDatabaseCount('individual_unit_positions', 0);
    }
}
