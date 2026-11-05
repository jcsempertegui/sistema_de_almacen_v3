<?php

namespace Tests\Feature\Inventory;

use App\Models\DocumentoInventario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class InitialStockImportTest extends TestCase
{
    use CreatesInventory;

    private function csv(array $c, string $cantidad = '4', string $identificador = ''): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('apertura.csv', "product_code;location_code;cantidad;individual_identifier\r\n".$c['producto']->codigo_interno.';'.$c['ubicacion']->codigo.';'.$cantidad.';'.$identificador."\r\n");
    }

    public function test_csv_preview_is_read_only_and_save_only_creates_a_draft(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor'])->get(route('importacion-existencias-iniciales.create'))->assertOk();
        $response = $this->post(route('importacion-existencias-iniciales.preview'), ['file' => $this->csv($c), 'fecha_documento' => '2026-10-19', 'fecha_corte' => '2026-10-18'])->assertOk()->assertSee('Guardar como borrador');
        $this->assertDatabaseCount('documentos_inventario', 0);
        $token = $response->viewData('token');
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => $token])->assertSessionHasNoErrors();
        $documento = DocumentoInventario::firstOrFail();
        $this->assertSame('csv', $documento->origen);
        $this->assertSame('draft', $documento->estado);
        $this->assertSame(64, strlen($documento->sha256_origen));
        $this->assertDatabaseCount('existencias_ubicacion', 0);
        $this->post(route('documentos-inventario.confirm', $documento), ['version_bloqueo' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('existencias_ubicacion', ['cantidad' => '4.000000']);
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => $token])->assertSessionHasNoErrors();
        $repeated = DocumentoInventario::latest('id')->firstOrFail();
        $this->get(route('documentos-inventario.show', $repeated))->assertOk()->assertSee('El mismo archivo CSV');
        $this->post(route('documentos-inventario.confirm', $repeated), ['version_bloqueo' => 1])->assertSessionHasErrors('lineas');
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }

    public function test_import_rejects_unknown_catalog_formulas_duplicates_and_changed_catalog(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        $header = "product_code;location_code;cantidad;individual_identifier\n";
        $line = $c['producto']->codigo_interno.';'.$c['ubicacion']->codigo.";1;\n";
        foreach ([$header."UNKNOWN;UNKNOWN;1;\n", $header.$line.$line, $header.$c['producto']->codigo_interno.';'.$c['ubicacion']->codigo.";=1+1;\n", "invalid;header\n"] as $content) {
            $this->post(route('importacion-existencias-iniciales.preview'), ['file' => UploadedFile::fake()->createWithContent('x.csv', $content), 'fecha_documento' => '2026-10-19', 'fecha_corte' => '2026-10-18'])->assertSessionHasErrors('file');
        }
        $response = $this->post(route('importacion-existencias-iniciales.preview'), ['file' => $this->csv($c), 'fecha_documento' => '2026-10-19', 'fecha_corte' => '2026-10-18'])->assertOk();
        $c['producto']->update(['activo' => false]);
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => $response->viewData('token')])->assertSessionHasErrors();
        $this->assertDatabaseCount('documentos_inventario', 0);
    }

    public function test_import_token_rejects_tampering_expiration_and_another_operator(): void
    {
        $c = $this->inventoryContext();
        $response = $this->signIn($c['actor'])->post(route('importacion-existencias-iniciales.preview'), ['file' => $this->csv($c), 'fecha_documento' => '2026-10-19', 'fecha_corte' => '2026-10-18'])->assertOk();
        $token = $response->viewData('token');
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => $token.'tampered'])->assertSessionHasErrors('file');
        $detalle = json_decode(Crypt::decryptString($token), true);
        $detalle['expires'] = now()->subMinute()->timestamp;
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => Crypt::encryptString(json_encode($detalle))])->assertStatus(419);
        $detalle['expires'] = now()->addMinute()->timestamp;
        $detalle['actor']++;
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => Crypt::encryptString(json_encode($detalle))])->assertStatus(419);
        $this->assertDatabaseCount('documentos_inventario', 0);
    }

    public function test_individual_import_aggregates_existing_identifiers_and_rejects_repeated_identity(): void
    {
        $c = $this->inventoryContext('individual');
        $this->signIn($c['actor']);
        $response = $this->post(route('importacion-existencias-iniciales.preview'), ['file' => $this->csv($c, '1', $c['unidad']->identificador), 'fecha_documento' => '2026-10-19', 'fecha_corte' => '2026-10-18'])->assertOk();
        $this->assertSame([$c['unidad']->id], $response->viewData('lineas')[0]['unit_ids']);
        $this->post(route('importacion-existencias-iniciales.store'), ['token' => $response->viewData('token')])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('unidades_linea_documento', 1);
        $this->assertDatabaseCount('posiciones_unidad_individual', 0);
    }
}
