<?php

namespace Tests\Feature;

use App\Actions\Deliveries\ConfirmDelivery;
use App\Actions\Deliveries\SaveDelivery;
use App\Actions\Inventory\ConfirmInventoryDocument;
use App\Actions\Inventory\SaveInventoryDocument;
use App\Actions\Loans\ConfirmLoan;
use App\Actions\Loans\SaveLoan;
use App\Actions\Returns\ConfirmReturn;
use App\Actions\Returns\SaveReturn;
use App\Models\IndividualUnit;
use App\Models\Permission;
use App\Models\User;
use App\Models\Worker;
use App\Services\ControlReports;
use App\Services\InventoryCorrections;
use App\Services\ReportCatalog;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesInventory;
use Tests\TestCase;

class DetailedReportsTest extends TestCase
{
    use CreatesInventory;

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    private function delivery(array $context, Worker $worker, bool $confirm = true, string $notes = 'Dotación semestral'): object
    {
        $doc = app(SaveDelivery::class)->handle($context['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'notes' => $notes, 'expected_line_count' => 1,
            'lines' => [['product_id' => $context['product']->id, 'location_id' => $context['location']->id, 'quantity' => '2', 'unit_ids' => []]]]);

        return $confirm ? app(ConfirmDelivery::class)->handle($context['actor'], $doc, 1) : $doc;
    }

    public function test_delivery_details_keep_historical_worker_and_line_units_with_named_filters(): void
    {
        $c = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '10');
        $worker = Worker::factory()->create(['nombres' => 'Ana', 'apellidos' => 'Pérez', 'cargo' => 'Instrumentista', 'area' => 'Mantenimiento']);
        $doc = $this->delivery($c, $worker);
        $worker->update(['nombres' => 'Nombre actualizado', 'cargo' => 'Otro cargo']);
        $response = $this->signIn($c['actor'])->get(route('control-reports.index', ['kind' => 'deliveries', 'worker_id' => $worker->id, 'category_id' => $c['product']->category_id]))->assertOk()
            ->assertSee('Ana Pérez')->assertSee('Instrumentista')->assertSee($doc->code)->assertSee($c['product']->name)
            ->assertSee('Una fila por línea o ítem del documento.');
        $row = $response->viewData('rows')->first();
        $this->assertSame('Ana Pérez', $row->trabajador);
        $this->assertSame('2.000000', $row->cantidad);
        $this->assertSame($c['product']->measurementUnit->symbol, $row->unidad);
        $this->assertSame(1, $response->viewData('summary')['Documentos']);
        $this->assertSame(1, $this->xpath($response->getContent())->query('//select[@name="worker_id"]')->length);
        $this->assertSame(0, $this->xpath($response->getContent())->query('//input[@name="worker_id"]')->length);
        $csv = $this->get(route('control-reports.index', ['kind' => 'deliveries', 'worker_id' => $worker->id, 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Cargo documental', $csv);
        $this->assertStringContainsString('Ana Pérez', $csv);
        $this->assertStringContainsString($doc->code, $csv);
        $this->assertStringContainsString('Dotación semestral', $csv);
    }

    public function test_multiple_identifiers_do_not_multiply_document_quantities(): void
    {
        $c = $this->inventoryContext('individual');
        $other = IndividualUnit::factory()->create(['product_id' => $c['product']->id, 'location_id' => null]);
        $data = $this->inventoryData($c, 'receipt', '2');
        $data['lines'][0]['unit_ids'] = [$c['unit']->id, $other->id];
        $doc = app(SaveInventoryDocument::class)->handle($c['actor'], null, $data);
        app(ConfirmInventoryDocument::class)->handle($c['actor'], $doc, 1);
        $rows = app(ControlReports::class)->query('remittances', [])->get();
        $this->assertCount(1, $rows);
        $this->assertSame('2.000000', $rows[0]->cantidad);
        $this->assertStringContainsString($other->identifier, $rows[0]->identificadores);
        $this->assertStringContainsString($c['unit']->identifier, $rows[0]->identificadores);
        $movements = app(ControlReports::class)->query('movements', [])->get();
        $this->assertCount(1, $movements);
        $this->assertSame('2.000000', $movements[0]->cantidad);
    }

    public function test_kardex_preserves_opening_balance_before_local_date_filter(): void
    {
        $c = $this->inventoryContext();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 03:30:00', 'UTC'));
        $this->postInventory($c, 'receipt', '10'); // September 30 in Bolivia.
        $this->travelTo(CarbonImmutable::parse('2026-10-01 04:30:00', 'UTC'));
        $this->postInventory($c, 'receipt', '5');
        $this->travelTo(CarbonImmutable::parse('2026-10-01 05:00:00', 'UTC'));
        $this->postInventory($c, 'issue', '2');
        $rows = app(ControlReports::class)->query('movements', ['from' => '2026-10-01', 'to' => '2026-10-01', 'product_id' => $c['product']->id])->get();
        $this->assertCount(2, $rows);
        $catalog = app(ReportCatalog::class);
        $this->assertSame('15', $catalog->cell('saldo', $rows[0]->saldo));
        $this->assertSame('13', $catalog->cell('saldo', $rows[1]->saldo));
        $this->assertSame('01/10/2026 00:30:00', $catalog->cell('registrado', $rows[0]->registrado));
        $this->signIn($c['actor'])->get(route('control-reports.index', ['kind' => 'stock', 'to' => '2026-09-30']))->assertOk();
        $stock = app(ControlReports::class)->query('stock', ['to' => '2026-09-30'])->first();
        $this->assertSame('10', $catalog->cell('cantidad', $stock->cantidad));
    }

    public function test_pending_reports_follow_reopened_custody_after_a_compensated_return(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 12:00:00', 'America/La_Paz'));
        $c = $this->inventoryContext('individual');
        $c['product']->update(['is_loanable' => true]);
        $this->postInventory($c, 'receipt', '1');
        $worker = Worker::factory()->create(['nombres' => 'Luis', 'apellidos' => 'Mendoza']);
        $loan = app(SaveLoan::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'due_date' => '2026-10-21', 'expected_line_count' => 1, 'lines' => [['individual_unit_id' => $c['unit']->id, 'location_id' => $c['location']->id]]]);
        $loan = app(ConfirmLoan::class)->handle($c['actor'], $loan, 1);
        $return = app(SaveReturn::class)->handle($c['actor'], null, ['loan_id' => $loan->id, 'document_date' => '2026-10-22', 'expected_line_count' => 1, 'lines' => [['loan_item_id' => $loan->lines()->first()->id, 'location_id' => $c['location']->id]]]);
        app(ConfirmReturn::class)->handle($c['actor'], $return, 1);
        $this->assertSame(0, app(ControlReports::class)->query('responsibilities', [])->count());
        $this->assertSame('Devuelto', app(ControlReports::class)->query('loans', [])->first()->situacion);
        $corrections = app(InventoryCorrections::class);
        $correction = $corrections->save($c['actor'], null, ['kind' => 'compensation', 'reason' => 'Recepción registrada por error', 'document_date' => '2026-10-31', 'source_kind' => 'returns', 'source_id' => $return->id]);
        $correction = $corrections->transition($c['actor'], $correction, 'request', $correction->lock_version);
        $approver = User::factory()->create(['role_id' => 1, 'name' => 'Laura Administradora']);
        $correction = $corrections->transition($approver, $correction, 'approve', $correction->lock_version, 'Revisión por otra persona');
        $corrections->execute($c['actor'], $correction, $correction->lock_version);
        $this->assertSame($correction->code, app(ControlReports::class)->query('returns', [])->first()->compensado_por);
        $this->assertSame('Laura Administradora', app(ControlReports::class)->query('corrections', [])->first()->autorizador);
        $this->assertNotNull(app(ControlReports::class)->query('movements', [])->whereNotNull('movimiento_original')->first());
        $worker->update(['is_active' => false]);
        $rows = app(ControlReports::class)->query('responsibilities', ['custody' => 'overdue'])->get();
        $this->assertCount(1, $rows);
        $this->assertSame('Luis Mendoza', $rows[0]->trabajador);
        $this->assertSame('Vencido', $rows[0]->situacion);
        $this->assertSame('Vencido', app(ControlReports::class)->query('loans', [])->first()->situacion);
        $this->signIn($c['actor']);
        foreach (['responsibilities', 'loans', 'returns', 'availability', 'corrections', 'movements', 'audit'] as $kind) {
            $this->get(route('control-reports.index', $kind))->assertOk();
            $this->assertNotEmpty($this->get(route('control-reports.index', ['kind' => $kind, 'format' => 'csv']))->assertOk()->streamedContent());
        }
        $this->get(route('control-reports.index', 'audit'))->assertSee('Corrección ejecutada');
    }

    public function test_print_and_csv_include_the_full_filtered_result_instead_of_the_first_page(): void
    {
        $c = $this->inventoryContext();
        $worker = Worker::factory()->create();
        for ($i = 0; $i < 31; $i++) {
            $this->delivery($c, $worker, false);
        }
        $this->signIn($c['actor']);
        $normal = $this->get(route('control-reports.index', 'deliveries'))->assertOk();
        $this->assertCount(30, $normal->viewData('rows'));
        $print = $this->get(route('control-reports.index', ['kind' => 'deliveries', 'format' => 'print', 'worker_id' => $worker->id]))->assertOk();
        $this->assertCount(31, $print->viewData('rows'));
        $this->assertSame(31, $this->xpath($print->getContent())->query('//article[contains(@class,"report-print-document")]')->length);
        $print->assertSee('Informe completo de la consulta')->assertSee('Imprimir / Guardar PDF');
        $this->assertSame(31, $print->viewData('summary')['Documentos']);
        $csv = $this->get(route('control-reports.index', ['kind' => 'deliveries', 'format' => 'csv']))->streamedContent();
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, substr($csv, 3));
        rewind($stream);
        $count = 0;
        while (fgetcsv($stream, null, ';', '"', '') !== false) {
            $count++;
        }
        fclose($stream);
        $this->assertSame(32, $count); // Header plus 31 lines.
    }

    public function test_group_navigation_uses_first_allowed_report_and_denies_other_tabs_and_exports(): void
    {
        $codes = array_map(fn ($kind) => app(ControlReports::class)->permission($kind), array_keys(ControlReports::LABELS));
        $ids = Permission::whereIn('code', array_diff($codes, ['reports.returns.view']))->pluck('id');
        DB::table('permission_role')->where('role_id', 1)->whereIn('permission_id', $ids)->update(['is_active' => false]);
        $user = User::factory()->create(['role_id' => 1]);
        $response = $this->signIn($user)->get(route('control-reports.index', 'returns'))->assertOk()->assertDontSee('Historial de préstamos');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//aside//section[@aria-label="Reportes"]//a')->length);
        $this->assertSame(1, $xpath->query('//aside//section[@aria-label="Reportes"]//a[@aria-current="page"]')->length);
        $this->get(route('control-reports.index', 'loans'))->assertForbidden();
        $export = Permission::where('code', 'reports.returns.export')->firstOrFail();
        DB::table('permission_role')->where('role_id', 1)->where('permission_id', $export->id)->update(['is_active' => false]);
        $this->get(route('control-reports.index', ['kind' => 'returns', 'format' => 'csv']))->assertForbidden();
        $this->get(route('control-reports.index', ['kind' => 'returns', 'format' => 'print']))->assertForbidden();
    }

    public function test_empty_csv_has_spanish_headers_and_unsafe_values_remain_escaped(): void
    {
        $c = $this->inventoryContext();
        $this->signIn($c['actor']);
        $empty = $this->get(route('control-reports.index', ['kind' => 'deliveries', 'format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Descripción del artículo', $empty);
        $worker = Worker::factory()->create(['nombres' => '=HYPERLINK(1)', 'apellidos' => '<script>alert(1)</script>']);
        $this->delivery($c, $worker, false, 'Detalle; con salto'."\n".'de línea');
        $this->get(route('control-reports.index', 'deliveries'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $csv = $this->get(route('control-reports.index', ['kind' => 'deliveries', 'format' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK(1)", $csv);
        $this->getJson(route('control-reports.index', ['kind' => 'deliveries', 'q' => ['invalid']]))->assertUnprocessable();
        $this->getJson(route('control-reports.index', ['kind' => 'deliveries', 'from' => '2026-10-31', 'to' => '2026-10-01']))->assertUnprocessable();
    }

    public function test_category_and_type_filters_preserve_document_numbers_and_distinct_summary(): void
    {
        $c = $this->inventoryContext();
        $other = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '3');
        $this->postInventory($c, 'issue', '1');
        $this->postInventory($other, 'receipt', '9');
        $response = $this->signIn($c['actor'])->get(route('control-reports.index', ['kind' => 'remittances', 'category_id' => $c['product']->category_id, 'type' => 'receipt']))->assertOk();
        $this->assertCount(1, $response->viewData('rows'));
        $this->assertSame(1, $response->viewData('summary')['Documentos']);
        $this->assertStringStartsWith('RG-IN-', $response->viewData('rows')->first()->documento);
        $this->assertSame('3.000000', $response->viewData('rows')->first()->cantidad);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(5, $xpath->query('//aside//section[@aria-label="Reportes"]//a')->length);
    }

    public function test_report_access_does_not_grant_document_access_or_display_a_forbidden_link(): void
    {
        $c = $this->inventoryContext();
        $worker = Worker::factory()->create();
        $doc = $this->delivery($c, $worker, false);
        $this->signIn($c['actor'])->get(route('control-reports.index', 'deliveries'))->assertOk()
            ->assertSee('href="'.route('worker-operations.show', ['deliveries', $doc->id]).'"', false);
        $permission = Permission::where('code', 'deliveries.view')->firstOrFail();
        DB::table('permission_role')->where('role_id', 1)->where('permission_id', $permission->id)->update(['is_active' => false]);
        $this->get(route('control-reports.index', 'deliveries'))->assertOk()->assertSee($doc->code)
            ->assertDontSee('href="'.route('worker-operations.show', ['deliveries', $doc->id]).'"', false);
        $this->get(route('worker-operations.show', ['deliveries', $doc->id]))->assertForbidden();
    }

    public function test_two_document_lines_keep_their_own_quantities_and_count_the_document_once(): void
    {
        $c = $this->inventoryContext();
        $other = $this->inventoryContext();
        $this->postInventory($c, 'receipt', '10');
        $this->postInventory($other, 'receipt', '10');
        $worker = Worker::factory()->create();
        $doc = app(SaveDelivery::class)->handle($c['actor'], null, ['worker_id' => $worker->id, 'document_date' => '2026-10-20', 'expected_line_count' => 2, 'lines' => [
            ['product_id' => $c['product']->id, 'location_id' => $c['location']->id, 'quantity' => '2', 'unit_ids' => []],
            ['product_id' => $other['product']->id, 'location_id' => $other['location']->id, 'quantity' => '3', 'unit_ids' => []],
        ]]);
        app(ConfirmDelivery::class)->handle($c['actor'], $doc, 1);
        $response = $this->signIn($c['actor'])->get(route('control-reports.index', 'deliveries'))->assertOk();
        $this->assertSame(['Registros' => 2, 'Documentos' => 1, 'Artículos' => 2, 'Trabajadores' => 1], $response->viewData('summary'));
        $this->assertSame(['2.000000', '3.000000'], $response->viewData('rows')->pluck('cantidad')->all());
    }
}
